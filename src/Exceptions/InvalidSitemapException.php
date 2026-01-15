<?php

namespace Coderden\SitemapParser\Exceptions;

class InvalidSitemapException extends SitemapException
{
    public function __construct(
        string $url, 
        string $reason, 
        ?string $xmlError = null, 
        int $code = 0, 
        ?\Throwable $previous = null
    ) {
        $message = sprintf('Invalid sitemap at %s: %s', $url, $reason);
        
        if ($xmlError) {
            $message .= sprintf(' (XML Error: %s)', $xmlError);
        }
        
        parent::__construct(
            $message, 
            $url, 
            [
                'reason' => $reason,
                'xml_error' => $xmlError
            ], 
            $code, 
            $previous
        );
    }
    
    public static function invalidXml(string $url, string $xmlError): self
    {
        return new self($url, 'Invalid XML format', $xmlError);
    }
    
    public static function unknownFormat(string $url): self
    {
        return new self($url, 'Unknown sitemap format');
    }
    
    public static function emptySitemap(string $url): self
    {
        return new self($url, 'Sitemap is empty or contains no valid URLs');
    }
}