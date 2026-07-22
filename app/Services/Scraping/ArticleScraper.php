<?php

namespace App\Services\Scraping;

use Carbon\CarbonImmutable;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetches a URL (static HTML only) and extracts its main article content using
 * a generic Readability pass. JS-rendered pages that return no article body
 * raise a ScrapeException so the caller can prompt for manual entry.
 */
class ArticleScraper
{
    /**
     * Minimum plain-text length for extracted content to count as an article.
     * Readability can return a few stray words for nav-only/JS-rendered pages
     * without raising an error; anything shorter than this is not postable.
     */
    protected const int MIN_CONTENT_LENGTH = 100;

    /**
     * Fetch and extract a single URL.
     *
     * @throws ScrapeException permanent failure (4xx, no readable content)
     * @throws ConnectionException transient network failure (retryable)
     */
    public function scrape(string $url): ScrapedContent
    {
        $this->assertFetchable($url);

        return $this->extract($this->fetch($url), $url);
    }

    /**
     * Basic SSRF guard: only http(s), and reject literal IPs in private/reserved
     * ranges plus obvious internal hostnames. Redirects are capped in fetch().
     * DNS names are not resolved here (avoids network in tests); this does not
     * defend against DNS-rebinding — acceptable for a single-user local tool.
     *
     * @throws ScrapeException when the URL must not be fetched
     */
    protected function assertFetchable(string $url): void
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = parse_url($url, PHP_URL_HOST);

        if (! in_array($scheme, ['http', 'https'], true) || blank($host)) {
            throw new ScrapeException('Only http and https URLs can be scraped.');
        }

        $host = trim($host, '[]'); // unwrap IPv6 literals

        if (in_array(strtolower($host), ['localhost'], true) || str_ends_with(strtolower($host), '.localhost')) {
            throw new ScrapeException('That host is not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            throw new ScrapeException('That host is not allowed.');
        }
    }

    /**
     * @throws ScrapeException on a 4xx response
     * @throws ConnectionException on network failure / timeout (retryable)
     */
    protected function fetch(string $url): string
    {
        $response = Http::withUserAgent(config('social.scraping.user_agent'))
            ->timeout(config('social.scraping.timeout'))
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml'])
            ->withOptions(['allow_redirects' => ['max' => 3]])
            ->get($url);

        // 5xx bubbles as a RequestException (retryable); 4xx is permanent.
        if ($response->clientError()) {
            throw new ScrapeException("The page returned HTTP {$response->status()}.");
        }

        $response->throw();

        return $response->body();
    }

    /**
     * Extract readable content from raw HTML. Separated from fetching so it can
     * be unit-tested directly.
     *
     * @throws ScrapeException when no article content can be extracted
     */
    public function extract(string $html, string $url): ScrapedContent
    {
        if (trim($html) === '') {
            throw new ScrapeException('The page was empty.');
        }

        $configuration = new Configuration([
            'fixRelativeURLs' => true,
            'originalURL' => $url,
        ]);

        $readability = new Readability($configuration);

        try {
            $readability->parse($html);
        } catch (ParseException $e) {
            throw new ScrapeException(
                'No readable content could be extracted (the page may be JavaScript-rendered).',
                previous: $e,
            );
        }

        // getContent() returns readable HTML; store decoded plain text.
        $body = $this->htmlToText($readability->getContent());

        if ($body === null || mb_strlen($body) < self::MIN_CONTENT_LENGTH) {
            throw new ScrapeException('No readable content could be extracted (the page may be JavaScript-rendered).');
        }

        return new ScrapedContent(
            title: $this->clamp($this->clean($readability->getTitle()), 255),
            body: $body,
            excerpt: $this->clean($readability->getExcerpt()),
            author: $this->clamp($this->clean($readability->getAuthor()), 255),
            ogImageUrl: $this->clamp($this->clean($readability->getImage()), 2048),
            publishedAt: $this->extractPublishedAt($html),
        );
    }

    /**
     * Best-effort published date from common meta tags.
     */
    protected function extractPublishedAt(string $html): ?CarbonImmutable
    {
        // Match both attribute orderings (property/name before or after content).
        $patterns = [
            '/<meta[^>]+(?:property|name)=["\'](?:article:published_time|date)["\'][^>]+content=["\']([^"\']+)["\']/i',
            '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+(?:property|name)=["\'](?:article:published_time|date)["\']/i',
            '/<time[^>]+datetime=["\']([^"\']+)["\']/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                try {
                    return CarbonImmutable::parse($matches[1]);
                } catch (Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Convert readable HTML to normalized, entity-decoded plain text. Block-level
     * closing tags become separators first, so adjacent blocks don't merge.
     */
    protected function htmlToText(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $html = preg_replace('/<\/(p|div|h[1-6]|li|br|section|article|blockquote)\s*>/i', "$0\n", $html) ?? $html;

        return $this->normalizeWhitespace($this->decodeEntities(strip_tags($html)));
    }

    protected function decodeEntities(string $value): string
    {
        return html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    protected function clean(?string $value): ?string
    {
        $value = $value === null ? null : trim($this->decodeEntities($value));

        return ($value === null || $value === '') ? null : $value;
    }

    protected function clamp(?string $value, int $length): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_strlen($value) > $length ? mb_substr($value, 0, $length) : $value;
    }

    protected function normalizeWhitespace(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(Str::of($value)->replaceMatches('/\s+/u', ' ')->value());

        return $value === '' ? null : $value;
    }
}
