<?php

namespace Coderden\SitemapParser\Exceptions;

use RuntimeException;

class SitemapException extends RuntimeException
{
    protected string $sitemapUrl;
    protected array $context = [];
    
    public function __construct(
        string $message, 
        string $sitemapUrl = '', 
        array $context = [], 
        int $code = 0, 
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->sitemapUrl = $sitemapUrl;
        $this->context = $context;
    }
    
    public function getSitemapUrl(): string
    {
        return $this->sitemapUrl;
    }
    
    public function getContext(): array
    {
        return $this->context;
    }
    
    public static function fromRequest(string $url, string $error, int $statusCode = 0): self
    {
        return new self(
            sprintf('Failed to fetch sitemap from %s: %s (Status: %d)', $url, $error, $statusCode),
            $url,
            ['status_code' => $statusCode, 'error' => $error]
        );
    }
    
    public static function fromParse(string $url, string $error): self
    {
        return new self(
            sprintf('Failed to parse sitemap from %s: %s', $url, $error),
            $url,
            ['parse_error' => $error]
        );
    }
}