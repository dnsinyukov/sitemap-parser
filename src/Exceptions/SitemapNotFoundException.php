<?php

namespace Coderden\SitemapParser\Exceptions;

class SitemapNotFoundException extends SitemapException
{
    public function __construct(
        string $url, 
        array $attemptedUrls = [], 
        int $code = 0, 
        ?\Throwable $previous = null
    ) {
        $message = sprintf(
            'Sitemap not found at %s. Attempted URLs: %s',
            $url,
            implode(', ', $attemptedUrls)
        );
        
        parent::__construct($message, $url, ['attempted_urls' => $attemptedUrls], $code, $previous);
    }
    
    public static function forDomain(string $domain, array $attemptedUrls = []): self
    {
        return new self($domain, $attemptedUrls);
    }
}