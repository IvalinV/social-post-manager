<?php

namespace App\Services\Posts;

use App\Enums\Platform;
use App\Models\Article;

/**
 * Renders a draft post body for a platform from a scraped article using a
 * hardcoded template (title + excerpt + link), truncated to fit the platform's
 * character limit. Text + link only — no media.
 */
class PostComposer
{
    private const string SEPARATOR = "\n\n";

    private const string ELLIPSIS = '…';

    /**
     * Minimum room (in weighted chars) worth keeping for an excerpt fragment;
     * below this the excerpt is dropped and only the title is kept.
     */
    private const int MIN_EXCERPT_CHARS = 15;

    /**
     * Build a draft body: title, a fitted excerpt, then the article URL.
     *
     * The URL is mandatory and is never truncated — a URL longer than the
     * platform limit is returned as-is (only realistic on LinkedIn). All length
     * budgeting uses the platform's WEIGHTED count (on X, every URL is 23),
     * including any URL embedded in the title/excerpt, so the result never
     * exceeds the limit. Note: emoji are counted by code point here; X counts
     * most emoji as 2, so an emoji-heavy body may be slightly under-counted.
     */
    public function render(Article $article, Platform $platform): string
    {
        $title = trim((string) $article->title);
        $excerpt = trim((string) $article->excerpt);
        $url = trim((string) $article->url);

        // The link is mandatory and (on X) counted at a fixed weight.
        $urlWeight = $url === '' ? 0 : $this->weightedLength($platform, $url);
        $hasLead = $title !== '' || $excerpt !== '';
        $budget = $platform->maxLength() - $urlWeight - ($hasLead && $url !== '' ? mb_strlen(self::SEPARATOR) : 0);

        $lead = $this->buildLead($platform, $title, $excerpt, $budget);

        return match (true) {
            $lead === '' => $url,
            $url === '' => $lead,
            default => $lead.self::SEPARATOR.$url,
        };
    }

    /**
     * Character length as the platform counts it. On X every URL is a fixed
     * weight (t.co) regardless of its real length; other platforms count real
     * length.
     */
    public function weightedLength(Platform $platform, string $text): int
    {
        $urlWeight = $platform->urlLength();
        $length = mb_strlen($text);

        if ($urlWeight === null) {
            return $length;
        }

        if (preg_match_all('/https?:\/\/\S+/i', $text, $matches) === 0) {
            return $length;
        }

        foreach ($matches[0] as $url) {
            // Drop trailing punctuation the greedy match may have captured; the
            // platform counts only the URL itself at the fixed weight.
            $url = rtrim($url, '.,;:!?)]}\'"');
            $length += $urlWeight - mb_strlen($url);
        }

        return $length;
    }

    /**
     * Remaining characters before the platform limit is exceeded (may be negative).
     */
    public function remaining(Platform $platform, string $text): int
    {
        return $platform->maxLength() - $this->weightedLength($platform, $text);
    }

    public function fits(Platform $platform, string $text): bool
    {
        return $this->remaining($platform, $text) >= 0;
    }

    private function buildLead(Platform $platform, string $title, string $excerpt, int $budget): string
    {
        if ($budget <= 0 || ($title === '' && $excerpt === '')) {
            return '';
        }

        if ($excerpt === '') {
            return $this->truncate($platform, $title, $budget);
        }

        if ($title === '') {
            return $this->truncate($platform, $excerpt, $budget);
        }

        $fittedTitle = $this->truncate($platform, $title, $budget);
        $remaining = $budget - $this->weightedLength($platform, $fittedTitle) - mb_strlen(self::SEPARATOR);

        // Not enough room for a meaningful excerpt — keep just the title.
        if ($remaining < self::MIN_EXCERPT_CHARS) {
            return $fittedTitle;
        }

        return $fittedTitle.self::SEPARATOR.$this->truncate($platform, $excerpt, $remaining);
    }

    /**
     * Truncate so the text's WEIGHTED length (with the ellipsis) fits $limit.
     * Starts near the raw limit and shrinks, so URLs embedded in the text —
     * which may weigh more than their raw length — are accounted for.
     */
    private function truncate(Platform $platform, string $text, int $limit): string
    {
        if ($limit <= 0) {
            return '';
        }

        if ($this->weightedLength($platform, $text) <= $limit) {
            return $text;
        }

        $target = $limit - mb_strlen(self::ELLIPSIS);

        for ($length = min(mb_strlen($text), $limit); $length > 0; $length--) {
            $candidate = rtrim(mb_substr($text, 0, $length));

            if ($this->weightedLength($platform, $candidate) <= $target) {
                return $candidate.self::ELLIPSIS;
            }
        }

        return self::ELLIPSIS;
    }
}
