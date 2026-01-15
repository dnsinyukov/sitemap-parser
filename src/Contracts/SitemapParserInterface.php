<?php

namespace Coderden\SitemapParser\Contracts;

interface SitemapParserInterface
{
    /**
     * Parse a sitemap from URL
     */
    public function parse(string $sitemapUrl): array;
    
    /**
     * Filter URLs according to criteria
     */
    public function filterUrls(array $urls, array $filters = []): array;
    
    /**
     * Search URLs by text
     */
    public function searchUrls(array $urls, string $search): array;
    
    /**
     * Group URLs by domain
     */
    public function groupByDomain(array $urls): array;
    
    /**
     * Get statistics about parsed URLs
     */
    public function getStats(array $urls): array;
    
    /**
     * Save URLs to file
     */
    public function saveToFile(array $urls, string $filename, string $format = 'txt'): bool;
    
    /**
     * Discover sitemaps on a domain
     */
    public function discoverSitemaps(string $domain): array;
}