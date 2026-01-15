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
    public static function parser(string $name = 'default'): SitemapParser
    {
        if (!isset(self::$instances[$name])) {
            self::$instances[$name] = new SitemapParser();
        }
        
        return self::$instances[$name];
    }
    
    /**
     * Quick parse sitemap
     * @throws SitemapException|SitemapNotFoundException|InvalidSitemapException
     */
    public static function parse(string $sitemapUrl, array $filters = []): array
    {
        return self::parser()->parse($sitemapUrl, $filters);
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
    public static function extractByPattern(string $sitemapUrl, string $pattern): array
    {
        return self::extractUrls($sitemapUrl, ['pattern' => $pattern]);
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