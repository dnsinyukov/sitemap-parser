# Sitemap Parser for PHP

[![PHP Version](https://img.shields.io/badge/php-8.1%2B-blue.svg)](https://packagist.org/packages/your-vendor/sitemap-parser)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)
[![Packagist Version](https://img.shields.io/packagist/v/your-vendor/sitemap-parser.svg)](https://packagist.org/packages/your-vendor/sitemap-parser)

A powerful, flexible PHP package for parsing sitemap.xml files with support for sitemap indexes, filtering, discovery, and multiple output formats.

## Features

- 🔍 **Parse any sitemap format** (XML, sitemap indexes, plain text, gzip compressed)
- 🌐 **Automatic sitemap discovery** via robots.txt and common paths
- 🎯 **Advanced filtering** by domain, pattern, priority, and more
- 📊 **Detailed statistics** and grouping capabilities
- 🔄 **Recursive parsing** of nested sitemap indexes
- 💾 **Multiple export formats** (TXT, JSON, CSV)
- 🚀 **Built-in rate limiting** and depth control
- 📈 **Priority-based sorting** and filtering
- 🛡️ **Comprehensive error handling** with custom exceptions
- 🔌 **PSR-compatible** with interface contracts

## Installation

```bash
composer require coderden/sitemap-parser
```

## Requirements

- PHP 8.1 or higher
- GuzzleHTTP 7.0 or higher
- ext-simplexml
- ext-libxml
- ext-zlib (for gzip support)

## Quick Start

```php
use Coderden\SitemapParser\SitemapParser;

$parser = new SitemapParser();
$result = $parser->parse('https://example.com/sitemap.xml');

echo "Found {$result['total']} URLs";
foreach ($result['urls'] as $url) {
    echo $url['url'] . "\n";
}
```

## Basic Usage

### Using SitemapParser

```php
// Create parser with custom configuration
$parser = new SitemapParser([
    'timeout' => 30,
    'max_depth' => 3,
    'max_urls' => 10000,
]);

// Parse a sitemap
$result = $parser->parse('https://example.com/sitemap.xml');

// Access parsed data
echo "Total URLs: " . $result['total'] . "\n";
echo "Sitemap URL: " . $result['sitemap_url'] . "\n";

foreach ($result['urls'] as $urlData) {
    echo "URL: " . $urlData['url'] . "\n";
    echo "Priority: " . ($urlData['priority'] ?? 'N/A') . "\n";
    echo "Last Modified: " . ($urlData['lastmod'] ?? 'N/A') . "\n";
    echo "---\n";
}
```

### Using SitemapHelper

```php
// Quick URL extraction
$urls = SitemapHelper::extractUrls('https://example.com/sitemap.xml');

// Parse with filtering
$result = SitemapHelper::parse('https://example.com/sitemap.xml', [
    'pattern' => '#/blog/#',
    'min_priority' => 0.5,
]);

// Auto-discover and parse all sitemaps
$siteData = SitemapHelper::parseAllSiteSitemaps('https://example.com');
```

## Configuration

The SitemapParser accepts an array of configuration options:

```php
$parser = new SitemapParser([
    // HTTP client options
    'timeout' => 30,
    'connect_timeout' => 10,
    'verify' => true, // SSL verification
    'allow_redirects' => true,
    
    // Sitemap specific options
    'max_depth' => 5,           // Maximum depth for sitemap indexes
    'max_urls' => 10000,        // Maximum URLs to parse
    'delay_between_requests' => 1, // Seconds between requests
    
    // HTTP headers
    'user_agent' => 'MySitemapParser/1.0',
    'headers' => [
        'Accept' => 'application/xml,text/xml',
        'Accept-Encoding' => 'gzip, deflate',
    ],
    
    // Proxy support
    'proxy' => 'http://proxy.example.com:8080',
    
    // Caching (requires PSR-6 implementation)
    'cache_ttl' => 3600,
]);
```

## Filtering URLs

```php
$parser = new SitemapParser();
$result = $parser->parse('https://example.com/sitemap.xml');

// Filter by pattern (regex)
$filtered = $parser->filterUrls($result['urls'], [
    'pattern' => '#^https://example\.com/blog/#',
]);

// Filter by domain
$filtered = $parser->filterUrls($result['urls'], [
    'domain' => 'example.com',
]);

// Filter by priority
$filtered = $parser->filterUrls($result['urls'], [
    'min_priority' => 0.7,
    'max_priority' => 1.0,
]);

// Filter by extension
$filtered = $parser->filterUrls($result['urls'], [
    'extension' => 'html',
]);

// Filter by path
$filtered = $parser->filterUrls($result['urls'], [
    'path_contains' => 'blog',
]);

// Multiple filters with sorting
$filtered = $parser->filterUrls($result['urls'], [
    'pattern' => '#/blog/#',
    'min_priority' => 0.5,
    'sort_by' => 'priority',
    'sort_direction' => 'desc',
    'limit' => 50,
]);
```

## Sitemap Discovery

Automatically discover sitemaps on a domain:

```php
$parser = new SitemapParser();

// Discover sitemaps at common locations
$sitemaps = $parser->discoverSitemaps('https://example.com');

if (!empty($sitemaps)) {
    foreach ($sitemaps as $sitemapUrl) {
        echo "Found sitemap: $sitemapUrl\n";
    }
} else {
    echo "No sitemaps found\n";
}
```

The discovery process checks:

- /sitemap.xml
- /sitemap_index.xml
- /sitemap/sitemap.xml
- /sitemap.xml.gz
- /robots.txt (for Sitemap directives)

## Statistics

Get detailed statistics about parsed URLs:

```php
$parser = new SitemapParser();
$result = $parser->parse('https://example.com/sitemap.xml');

$stats = $parser->getStats($result['urls']);

echo "Total URLs: " . $stats['total_urls'] . "\n";
echo "Domains:\n";
foreach ($stats['domains'] as $domain => $count) {
    echo "  $domain: $count\n";
}
echo "File extensions:\n";
foreach ($stats['extensions'] as $ext => $count) {
    echo "  $ext: $count\n";
}
echo "URLs with priority: " . $stats['urls_with_priority'] . "\n";
echo "URLs with lastmod: " . $stats['urls_with_lastmod'] . "\n";
```

### Group URLs by domain:

```php
$grouped = $parser->groupByDomain($result['urls']);

foreach ($grouped as $domain => $urls) {
    echo "$domain: " . count($urls) . " URLs\n";
}
```

## Export Formats

Export parsed URLs to various formats:

```php
$parser = new SitemapParser();
$result = $parser->parse('https://example.com/sitemap.xml');

// Export as plain text (URLs only)
$parser->saveToFile($result['urls'], 'urls.txt', 'txt');

// Export as JSON (full data)
$parser->saveToFile($result['urls'], 'urls.json', 'json');

// Export as CSV (with metadata)
$parser->saveToFile($result['urls'], 'urls.csv', 'csv');
```

## Error Handling

The package provides comprehensive error handling through custom exceptions:

```php
try {
    $parser = new SitemapParser();
    $result = $parser->parse('https://example.com/sitemap.xml');
    
} catch (SitemapNotFoundException $e) {
    echo "Sitemap not found: " . $e->getMessage() . "\n";
    echo "Attempted URLs: " . implode(', ', $e->getContext()['attempted_urls'] ?? []) . "\n";
    
} catch (InvalidSitemapException $e) {
    echo "Invalid sitemap: " . $e->getMessage() . "\n";
    echo "Reason: " . ($e->getContext()['reason'] ?? 'Unknown') . "\n";
    
} catch (SitemapException $e) {
    echo "Sitemap error: " . $e->getMessage() . "\n";
    echo "Sitemap URL: " . $e->getSitemapUrl() . "\n";
    
} catch (\Exception $e) {
    echo "Unexpected error: " . $e->getMessage() . "\n";
}
```

## Advanced Usage

### Batch Processing

```php
$parser = new SitemapParser();

$sitemaps = [
    'https://example.com/sitemap.xml',
    'https://example.com/sitemap_blog.xml',
];

$allUrls = [];
foreach ($sitemaps as $sitemapUrl) {
    try {
        $result = $parser->parse($sitemapUrl);
        $allUrls = array_merge($allUrls, $result['urls']);
    } catch (SitemapException $e) {
        error_log("Failed to parse $sitemapUrl: " . $e->getMessage());
    }
    
    // Respect delay between requests
    sleep(1);
}

// Remove duplicates
$uniqueUrls = [];
$seen = [];
foreach ($allUrls as $urlData) {
    if (!isset($seen[$urlData['url']])) {
        $uniqueUrls[] = $urlData;
        $seen[$urlData['url']] = true;
    }
}
```


### Integration with Web Crawlers

```php
use YourVendor\SitemapParser\SitemapParser;
use GuzzleHttp\Client;

class SiteCrawler {
    private SitemapParser $sitemapParser;
    private Client $httpClient;
    
    public function __construct() {
        $this->sitemapParser = new SitemapParser();
        $this->httpClient = new Client(['timeout' => 30]);
    }
    
    public function crawlSite(string $domain): array {
        // Discover and parse sitemap
        $sitemaps = $this->sitemapParser->discoverSitemaps($domain);
        
        if (empty($sitemaps)) {
            throw new \Exception("No sitemaps found for $domain");
        }
        
        $allData = [];
        foreach ($sitemaps as $sitemapUrl) {
            $result = $this->sitemapParser->parse($sitemapUrl);
            
            // Process each URL
            foreach ($result['urls'] as $urlData) {
                $pageData = $this->crawlPage($urlData['url']);
                $allData[] = array_merge($urlData, $pageData);
            }
        }
        
        return $allData;
    }
    
    private function crawlPage(string $url): array {
        // Implement page crawling logic
        return ['title' => 'Example', 'content' => '...'];
    }
}
```

## Examples

### Example 1: Extract Blog URLs from Sitemap

```php
use YourVendor\SitemapParser\SitemapHelper;

$blogUrls = SitemapHelper::extractByPattern(
    'https://example.com/sitemap.xml',
    '#^https://example\.com/blog/#'
);

file_put_contents('blog_urls.txt', implode("\n", $blogUrls));
```

### Example 2: Monitor Sitemap Changes

```php
$parser = new SitemapParser();

// Parse sitemap today
$today = $parser->parse('https://example.com/sitemap.xml');
file_put_contents('sitemap_today.json', json_encode($today['urls']));

// Tomorrow, parse again and compare
$tomorrow = $parser->parse('https://example.com/sitemap.xml');

$todayUrls = array_column($today['urls'], 'url');
$tomorrowUrls = array_column($tomorrow['urls'], 'url');

$newUrls = array_diff($tomorrowUrls, $todayUrls);
$removedUrls = array_diff($todayUrls, $tomorrowUrls);

echo "New URLs: " . count($newUrls) . "\n";
echo "Removed URLs: " . count($removedUrls) . "\n";
```

### Example 3: Generate Site Structure Report

```php
$parser = new SitemapParser();
$result = $parser->parse('https://example.com/sitemap.xml');

$stats = $parser->getStats($result['urls']);
$grouped = $parser->groupByDomain($result['urls']);

$report = [
    'generated_at' => date('Y-m-d H:i:s'),
    'sitemap_url' => $result['sitemap_url'],
    'total_urls' => $result['total'],
    'statistics' => $stats,
    'domains' => array_keys($grouped),
    'urls_by_domain' => array_map('count', $grouped),
];

file_put_contents('sitemap_report.json', json_encode($report, JSON_PRETTY_PRINT));
```