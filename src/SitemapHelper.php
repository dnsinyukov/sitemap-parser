<?php

namespace Coderden\SitemapParser;

use Coderden\SitemapParser\Exceptions\{
    SitemapException,
    SitemapNotFoundException,
    InvalidSitemapException
};

class SitemapHelper
{
    private static array $instances = [];
    
    /**
     * Get sitemap parser instance
     */
    public static function parser(string $name = 'default', array $config = []): SitemapParser
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new SitemapParser($config);
        }
        
        return self::$instances[$name];
    }
    
    /**
     * Quick parse sitemap
     * @throws SitemapException|SitemapNotFoundException|InvalidSitemapException
     */
    public static function parse(string $sitemapUrl, array $filters = [], array $config = []): array
    {
        return self::parser('default', $config)->parse($sitemapUrl, $filters);
    }
    
    /**
     * Discover sitemaps on domain
     * @throws SitemapNotFoundException
     */
    public static function discover(string $domain): array
    {
        return self::parser()->discoverSitemaps($domain);
    }
    
    /**
     * Extract only URLs from sitemap
     * @throws SitemapException|SitemapNotFoundException|InvalidSitemapException
     */
    public static function extractUrls(string $sitemapUrl, array $filters = []): array
    {
        $result = self::parse($sitemapUrl, $filters);
        return array_column($result['urls'], 'url');
    }
    
    /**
     * Extract URLs by pattern
     * @throws SitemapException|SitemapNotFoundException|InvalidSitemapException
     */
    public static function extractByPattern(string $sitemapUrl, string $pattern, array $excludePatterns = []): array
    {
        $filters = ['pattern' => $pattern];
        
        if (!empty($excludePatterns)) {
            if (is_string($excludePatterns)) {
                $filters['exclude_pattern'] = $excludePatterns;
            } else {
                // Combine multiple exclude patterns into one
                $excludePattern = '/(' . implode('|', array_map('preg_quote', $excludePatterns)) . ')/';
                $filters['exclude_pattern'] = $excludePattern;
            }
        }

        return self::extractUrls($sitemapUrl, ['pattern' => $pattern]);
    }

        /**
     * Extract URLs with skipping first N
     */
    public static function extractWithSkip(string $sitemapUrl, int $skipFirst = 0, int $skipLast = 0, array $filters = []): array
    {
        $filters['skip_first'] = $skipFirst;
        $filters['skip_last'] = $skipLast;
        
        return self::extractUrls($sitemapUrl, $filters);
    }

    /**
     * Extract URLs with taking only first N
     */
    public static function extractFirst(string $sitemapUrl, int $limit, array $filters = []): array
    {
        $filters['take_first'] = $limit;
        return self::extractUrls($sitemapUrl, $filters);
    }
    
    /**
     * Extract URLs with taking only last N
     */
    public static function extractLast(string $sitemapUrl, int $limit, array $filters = []): array
    {
        $filters['take_last'] = $limit;
        return self::extractUrls($sitemapUrl, $filters);
    }

    /**
     * Extract every Nth URL
     */
    public static function extractEveryNth(string $sitemapUrl, int $nth, array $filters = []): array
    {
        $filters['take_every_nth'] = $nth;
        return self::extractUrls($sitemapUrl, filters: $filters);
    }
    
    /**
     * Extract random URLs
     */
    public static function extractRandom(string $sitemapUrl, int $count, array $filters = []): array
    {
        $filters['take_random'] = $count;
        return self::extractUrls($sitemapUrl, $filters);
    }

        /**
     * Extract URLs excluding specific patterns
     */
    public static function extractExcluding(string $sitemapUrl, array $excludePatterns, array $filters = []): array
    {
        if (is_string($excludePatterns)) {
            $filters['exclude_pattern'] = $excludePatterns;
        } else {
            $filters['exclude_pattern'] = '/(' . implode('|', array_map('preg_quote', $excludePatterns)) . ')/';
        }
        
        return self::extractUrls($sitemapUrl, filters: $filters);
    }

    /**
     * Extract URLs excluding specific strings
     */
    public static function extractExcludingStrings(string $sitemapUrl, array $excludeStrings, array $filters = []): array
    {
        $filters['exclude_contain'] = $excludeStrings;
        return self::extractUrls($sitemapUrl, $filters);
    }
    
    /**
     * Extract URLs excluding specific domains
     */
    public static function extractExcludingDomains(string $sitemapUrl, array $excludeDomains, array $filters = []): array
    {
        $filters['exclude_domain'] = $excludeDomains;
        return self::extractUrls($sitemapUrl, filters: $filters);
    }

        /**
     * Extract URLs with offset and limit
     */
    public static function extractWithOffset(string $sitemapUrl, int $offset = 0, ?int $limit = null, array $filters = []): array
    {
        $filters['offset'] = $offset;
        if ($limit !== null) {
            $filters['limit'] = $limit;
        }
        
        return self::extractUrls($sitemapUrl, $filters);
    }
    
    /**
     * Parse all sitemaps on site (auto-discovery)
     * @throws SitemapNotFoundException
     */
    public static function parseAllSiteSitemaps(string $domain, array $filters = []): array
    {
        try {
            $sitemaps = self::discover($domain);
        } catch (SitemapNotFoundException $e) {
            // Try standard path as fallback
            $sitemaps = [rtrim($domain, '/') . '/sitemap.xml'];
        }
        
        $allUrls = [];
        $allErrors = [];
        
        foreach ($sitemaps as $sitemapUrl) {
            try {
                $result = self::parse($sitemapUrl, $filters);
                $allUrls = array_merge($allUrls, $result['urls']);
                $allErrors = array_merge($allErrors, $result['errors']);
            } catch (\Exception $e) {
                $allErrors[] = [
                    'sitemap' => $sitemapUrl,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ];
            }
        }
        
        // Remove duplicate URLs
        $uniqueUrls = [];
        $seen = [];
        
        foreach ($allUrls as $urlData) {
            if (!isset($seen[$urlData['url']])) {
                $uniqueUrls[] = $urlData;
                $seen[$urlData['url']] = true;
            }
        }
        
        return [
            'urls' => $uniqueUrls,
            'total' => count($uniqueUrls),
            'sitemaps' => $sitemaps,
            'errors' => $allErrors,
        ];
    }
}