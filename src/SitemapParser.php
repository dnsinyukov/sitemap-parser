<?php

namespace Coderden\SitemapParser;

use Coderden\SitemapParser\Contracts\SitemapParserInterface;
use Coderden\SitemapParser\Exceptions\SitemapException;
use Coderden\SitemapParser\Exceptions\SitemapNotFoundException;
use Coderden\SitemapParser\Exceptions\InvalidSitemapException;
use SimpleXMLElement;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Cache\CacheItemPoolInterface;

class SitemapParser implements SitemapParserInterface
{
    private Client $httpClient;
    private array $options;
    private array $parsedUrls = [];
    private array $errors = [];

    private ?CacheItemPoolInterface $cache;
    private int $cacheTtl;

    private array $rateLimits = [];
    private int $maxUrls = 10000;
    
    public function __construct(array $config = [], ?CacheItemPoolInterface $cache = null)
    {
        $defaultConfig = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => false,
            'max_depth' => 5,
            'max_urls' => 10000,
            'delay_between_requests' => 1,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'headers' => [
                'Accept' => 'application/xml,text/xml,text/plain',
                'Accept-Encoding' => 'gzip, deflate',
            ],
        ];
        
        $this->httpClient = new Client(array_merge($defaultConfig, $config));
        $this->options = array_merge($defaultConfig, $config);

        $this->cache = $cache;
        $this->cacheTtl = $config['cache_ttl'] ?? 3600;

        $this->maxUrls = $config['max_urls'] ?? 10000;
        $this->rateLimits = [
            'delay' => $config['delay_between_requests'] ?? 1,
            'requests_per_minute' => $config['requests_per_minute'] ?? 60,
        ];
    }
    
    /**
     * Parse a sitemap from URL
     */
    public function parse(string $sitemapUrl, array $filters = []): array
    {
        $this->parsedUrls = [];
        $this->errors = [];
        
        try {
            $this->parseSitemapRecursive($sitemapUrl);
        } catch (SitemapException $e) {
            $this->errors[] = [
                'url' => $sitemapUrl,
                'error' => $e->getMessage(),
                'context' => $e->getContext(),
            ];
            throw $e;
        } catch (\Exception $e) {
            $this->errors[] = [
                'url' => $sitemapUrl,
                'error' => $e->getMessage(),
            ];
            throw SitemapException::fromParse($sitemapUrl, $e->getMessage());
        }
        
        if (empty($this->parsedUrls)) {
            throw InvalidSitemapException::emptySitemap($sitemapUrl);
        }

        $urls = $this->parsedUrls;

        // Apply filters if provided
        if (!empty($filters)) {
            $urls = $this->filterUrls($urls, $filters);
        }
        
        return [
            'urls' => $urls,
            'total' => count($urls),
            'original_total' => count($this->parsedUrls), // Total before filtering
            'errors' => $this->errors,
            'sitemap_url' => $sitemapUrl,
        ];
    }
    
    /**
     * Recursive sitemap parsing
     */
    private function parseSitemapRecursive(string $sitemapUrl, int $depth = 0, int $maxDepth = 5): void
    {
        // Проверяем лимит URL
        if (count($this->parsedUrls) >= $this->maxUrls) {
            throw new \RuntimeException("Maximum URLs limit reached: {$this->maxUrls}");
        }
        
        // Применяем rate limiting
        if ($this->rateLimits['delay'] > 0) {
            usleep($this->rateLimits['delay'] * 1000000);
        }

        $maxDepthLimit = $maxDepth ?? $this->options['max_depth'] ?? 5;

        if ($depth > $maxDepthLimit) {
            throw new SitemapException(
                "Maximum sitemap depth exceeded: " . $maxDepthLimit,
                $sitemapUrl,
                ['depth' => $depth, 'max_depth' => $maxDepthLimit]
            );
        }
        
        if (count($this->parsedUrls) >= ($this->options['max_urls'] ?? 10000)) {
            throw new SitemapException(
                "Maximum URLs limit reached: " . ($this->options['max_urls'] ?? 10000),
                $sitemapUrl,
                ['current_count' => count($this->parsedUrls), 'max_urls' => $this->options['max_urls'] ?? 10000]
            );
        }
        
        // Apply rate limiting
        if ($depth > 0 && isset($this->options['delay_between_requests'])) {
            sleep($this->options['delay_between_requests']);
        }
        
        try {
            $response = $this->httpClient->request('GET', $sitemapUrl, $this->options);
            $content = (string) $response->getBody();
            
            // Handle gzip compression
            $contentType = $response->getHeaderLine('Content-Type');
            $contentEncoding = $response->getHeaderLine('Content-Encoding');
            $urlExtension = pathinfo(parse_url($sitemapUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
            
            if (str_contains($contentEncoding, 'gzip') || $urlExtension === 'gz') {
                if (!function_exists('gzdecode')) {
                    throw new SitemapException(
                        'gzip decompression not available (zlib extension missing)',
                        $sitemapUrl
                    );
                }
                
                $content = gzdecode($content);
                if ($content === false) {
                    throw new SitemapException(
                        'Failed to decode gzip content',
                        $sitemapUrl
                    );
                }
            }
            
            if ($this->isXml($content)) {
                $this->parseXmlSitemap($content, $sitemapUrl, $depth);
            } else {
                $this->parseTextSitemap($content, $sitemapUrl);
            }
            
        } catch (RequestException $e) {
            throw SitemapException::fromRequest(
                $sitemapUrl,
                $e->getMessage(),
                $e->getResponse() ? $e->getResponse()->getStatusCode() : 0
            );
        }
    }
    
    private function parseXmlSitemap(string $xmlContent, string $sitemapUrl, int $depth, int $maxDepth = 5): void
    {
        libxml_use_internal_errors(true);
        
        try {
            $xml = simplexml_load_string($xmlContent);
            
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errorMessages = array_map(fn($e) => $e->message, $errors);
                
                throw InvalidSitemapException::invalidXml(
                    $sitemapUrl,
                    implode(', ', $errorMessages)
                );
            }
            
            // Проверяем, является ли это индексом sitemap
            if (isset($xml->sitemap)) {
                $this->parseSitemapIndex($xml, $sitemapUrl, $depth, $maxDepth);
            } 
            // Проверяем, является ли это обычным sitemap
            elseif (isset($xml->url)) {
                $this->parseUrlSet($xml, $sitemapUrl);
            }
            // Проверяем формат namespace
            elseif ($xml->getName() === 'urlset' || $xml->getName() === 'sitemapindex') {
                $namespaces = $xml->getNamespaces(true);
                
                if ($xml->getName() === 'sitemapindex') {
                    $this->parseSitemapIndex($xml, $sitemapUrl, $depth, $maxDepth);
                } else {
                    $this->parseUrlSet($xml, $sitemapUrl);
                }
            } else {
                throw new \RuntimeException("Unknown sitemap format");
            }
            
        } catch (InvalidSitemapException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw InvalidSitemapException::fromParse($sitemapUrl, $e->getMessage());
        }
    }
    
    /**
     * Discover sitemaps on a domain
     */
    public function discoverSitemaps(string $domain): array
    {
        $sitemapPaths = [
            '/sitemap.xml',
            '/sitemap_index.xml',
            '/sitemap/sitemap.xml',
            '/sitemap/sitemap_index.xml',
            '/sitemap.xml.gz',
            '/sitemap/sitemap.xml.gz',
            '/robots.txt',
        ];
        
        $foundSitemaps = [];
        $attemptedUrls = [];
        
        foreach ($sitemapPaths as $path) {
            $url = rtrim($domain, '/') . $path;
            $attemptedUrls[] = $url;
            
            try {
                $response = $this->httpClient->request('HEAD', $url, [
                    'timeout' => 5,
                    'allow_redirects' => true,
                    'headers' => $this->options['headers'] ?? [],
                ]);
                
                if ($response->getStatusCode() === 200) {
                    if ($path === '/robots.txt') {
                        $robotsContent = (string) $this->httpClient->request('GET', $url)->getBody();
                        $this->parseRobotsTxt($robotsContent, $domain, $foundSitemaps);
                    } else {
                        $foundSitemaps[] = $url;
                    }
                }
            } catch (\Exception $e) {
                // Continue trying other paths
                continue;
            }
        }
        
        if (empty($foundSitemaps)) {
            throw SitemapNotFoundException::forDomain($domain, $attemptedUrls);
        }
        
        return array_unique($foundSitemaps);
    }

    public function parseWithCache(string $sitemapUrl): array
    {
        if ($this->cache) {
            $cacheKey = 'sitemap_' . md5($sitemapUrl);
            $cacheItem = $this->cache->getItem($cacheKey);
            
            if ($cacheItem->isHit()) {
                return $cacheItem->get();
            }
            
            $result = $this->parse($sitemapUrl);
            $cacheItem->set($result)->expiresAfter($this->cacheTtl);
            $this->cache->save($cacheItem);
            
            return $result;
        }
        
        return $this->parse($sitemapUrl);
    }
    
    /**
     * Parsing a set of URLs
     */
    private function parseUrlSet(SimpleXMLElement $xml, string $sitemapUrl): void
    {
        foreach ($xml->url as $url) {
            $loc = (string) $url->loc;
            
            if (empty($loc)) {
                // Trying to get loc from the namespace
                $namespaces = $url->getNamespaces(true);
                foreach ($namespaces as $prefix => $namespace) {
                    $children = $url->children($namespace);
                    if (isset($children->loc)) {
                        $loc = (string) $children->loc;
                        break;
                    }
                }
            }
            
            if (!empty($loc)) {
                $urlData = [
                    'url' => $loc,
                    'sitemap' => $sitemapUrl,
                    'priority' => isset($url->priority) ? (float) $url->priority : null,
                    'changefreq' => isset($url->changefreq) ? (string) $url->changefreq : null,
                    'lastmod' => isset($url->lastmod) ? (string) $url->lastmod : null,
                ];
                
                $this->parsedUrls[] = $urlData;
            }
        }
    }
    
    /**
     * Parsing a text sitemap (each line is a URL)
     */
    private function parseTextSitemap(string $content, string $sitemapUrl): void
    {
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (!empty($line) && filter_var($line, FILTER_VALIDATE_URL)) {
                $this->parsedUrls[] = [
                    'url' => $line,
                    'sitemap' => $sitemapUrl,
                    'priority' => null,
                    'changefreq' => null,
                    'lastmod' => null,
                ];
            }
        }
    }
    
    /**
     * Checking if content is XML
     */
    private function isXml(string $content): bool
    {
        $content = trim($content);
        
        // Check for the presence of an XML declaration or root tag
        return str_starts_with($content, '<?xml') || 
               str_starts_with($content, '<urlset') || 
               str_starts_with($content, '<sitemapindex') ||
               str_contains($content, '<urlset') ||
               str_contains($content, '<sitemapindex');
    }
    
    /**
     * URL filtering by pattern
     */
    public function filterUrls(array $urls, array $filters = []): array
    {
        $filtered = $urls;
        
        // Regular expression filter
        if (!empty($filters['pattern'])) {
            $pattern = $filters['pattern'];
            $filtered = array_filter($filtered, function ($urlData) use ($pattern) {
                return preg_match($pattern, $urlData['url']);
            });
        }
        
        // Domain filter
        if (!empty($filters['domain'])) {
            $domain = $filters['domain'];
            $filtered = array_filter($filtered, function ($urlData) use ($domain) {
                $urlDomain = parse_url($urlData['url'], PHP_URL_HOST);
                return $urlDomain === $domain;
            });
        }
        
        // Path filter
        if (!empty($filters['path_contains'])) {
            $pathContains = $filters['path_contains'];
            $filtered = array_filter($filtered, function ($urlData) use ($pathContains) {
                $path = parse_url($urlData['url'], PHP_URL_PATH);
                return str_contains($path, $pathContains);
            });
        }
        
        // Filter by extension
        if (!empty($filters['extension'])) {
            $extension = $filters['extension'];
            $filtered = array_filter($filtered, function ($urlData) use ($extension) {
                $path = parse_url($urlData['url'], PHP_URL_PATH);
                $ext = pathinfo($path, PATHINFO_EXTENSION);
                return empty($extension) || $ext === $extension;
            });
        }
        
        // Filter by priority
        if (isset($filters['min_priority'])) {
            $minPriority = (float) $filters['min_priority'];
            $filtered = array_filter($filtered, function ($urlData) use ($minPriority) {
                return $urlData['priority'] === null || $urlData['priority'] >= $minPriority;
            });
        }
        
        // Sorting
        if (!empty($filters['sort_by'])) {
            $sortBy = $filters['sort_by'];
            $direction = $filters['sort_direction'] ?? 'asc';
            
            usort($filtered, function ($a, $b) use ($sortBy, $direction) {
                $valueA = $a[$sortBy] ?? '';
                $valueB = $b[$sortBy] ?? '';
                
                if ($direction === 'desc') {
                    return $valueB <=> $valueA;
                }
                
                return $valueA <=> $valueB;
            });
        }
        
        // Limit
        if (!empty($filters['limit'])) {
            $filtered = array_slice($filtered, 0, (int) $filters['limit']);
        }
        
        return array_values($filtered);
    }
    
    /**
     * Seaching a URL in a sitemap
     */
    public function searchUrls(array $urls, string $search): array
    {
        $search = strtolower($search);
        
        return array_filter($urls, function ($urlData) use ($search) {
            return str_contains(strtolower($urlData['url']), $search);
        });
    }
    
    /**
     * Grouping URLs by domain
     */
    public function groupByDomain(array $urls): array
    {
        $grouped = [];
        
        foreach ($urls as $urlData) {
            $domain = parse_url($urlData['url'], PHP_URL_HOST);
            
            if (!isset($grouped[$domain])) {
                $grouped[$domain] = [];
            }
            
            $grouped[$domain][] = $urlData;
        }
        
        return $grouped;
    }
    
    /**
     * Getting sitemap statistics
     */
    public function getStats(array $urls): array
    {
        $domains = [];
        $extensions = [];
        $priorities = [];
        
        foreach ($urls as $urlData) {
            // Domain statistics
            $domain = parse_url($urlData['url'], PHP_URL_HOST);
            $domains[$domain] = ($domains[$domain] ?? 0) + 1;
            
            // Extension statistics
            $path = parse_url($urlData['url'], PHP_URL_PATH);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (!empty($ext)) {
                $extensions[$ext] = ($extensions[$ext] ?? 0) + 1;
            }
            
            // Priority statistics
            if ($urlData['priority'] !== null) {
                $priority = (string) $urlData['priority'];
                $priorities[$priority] = ($priorities[$priority] ?? 0) + 1;
            }
        }
        
        return [
            'total_urls' => count($urls),
            'domains' => $domains,
            'extensions' => $extensions,
            'priorities' => $priorities,
            'urls_with_priority' => count(array_filter($urls, fn($u) => $u['priority'] !== null)),
            'urls_with_lastmod' => count(array_filter($urls, fn($u) => !empty($u['lastmod']))),
        ];
    }
    
    /**
     * Saving a URL to a file
     */
    public function saveToFile(array $urls, string $filename, string $format = 'txt'): bool
    {
        try {
            switch (strtolower($format)) {
                case 'json':
                    $content = json_encode($urls, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    break;
                    
                case 'csv':
                    $csv = fopen('php://temp', 'r+');
                    fputcsv($csv, ['URL', 'Priority', 'Change Frequency', 'Last Modified', 'Sitemap']);
                    foreach ($urls as $urlData) {
                        if (is_array($urlData)) {
                            fputcsv($csv, [
                                $urlData['url'],
                                $urlData['priority'] ?? '',
                                $urlData['changefreq'] ?? '',
                                $urlData['lastmod'] ?? '',
                                $urlData['sitemap'] ?? '',
                            ]);
                        } else {
                            fputcsv($csv, $urlData);
                        }
                        
                    }
                    rewind($csv);
                    $content = stream_get_contents($csv);
                    fclose($csv);
                    break;
                    
                case 'txt':
                default:
                    $content = implode("\n", $urls);
                    break;
            }
            
            return file_put_contents($filename, $content) !== false;
            
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Parsing robots.txt to find sitemap directives
     */
    private function parseRobotsTxt(string $content, string $domain, array &$foundSitemaps): void
    {
        $lines = explode("\n", $content);
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (str_starts_with(strtolower($line), 'sitemap:')) {
                $sitemapUrl = trim(substr($line, 8));
                
                // If a relative URL is specified, make it absolute
                if (!parse_url($sitemapUrl, PHP_URL_SCHEME)) {
                    if (str_starts_with($sitemapUrl, '/')) {
                        $sitemapUrl = rtrim($domain, '/') . $sitemapUrl;
                    } else {
                        $sitemapUrl = rtrim($domain, '/') . '/' . $sitemapUrl;
                    }
                }
                
                $foundSitemaps[] = $sitemapUrl;
            }
        }
    }

    /**
     * Sitemap index parsing
     */
    private function parseSitemapIndex(SimpleXMLElement $xml, string $parentUrl, int $depth, int $maxDepth): void
    {
        foreach ($xml->sitemap as $sitemap) {
            $loc = (string) $sitemap->loc;
            
            if (empty($loc)) {
                // Let's try to get loc from namespace
                $namespaces = $sitemap->getNamespaces(true);
                foreach ($namespaces as $prefix => $namespace) {
                    $children = $sitemap->children($namespace);
                    if (isset($children->loc)) {
                        $loc = (string) $children->loc;
                        break;
                    }
                }
            }
            
            if (!empty($loc)) {
                try {
                    $this->parseSitemapRecursive($loc, $depth + 1, $maxDepth);
                } catch (\Exception $e) {
                    $this->errors[] = [
                        'url' => $loc,
                        'error' => $e->getMessage(),
                        'parent' => $parentUrl,
                    ];
                }
            }
        }
    }
}