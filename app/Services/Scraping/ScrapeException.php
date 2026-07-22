<?php

namespace App\Services\Scraping;

use RuntimeException;

/**
 * Thrown for permanent scrape failures that should NOT be retried — a 4xx
 * response, or a page with no extractable article content (often JS-rendered).
 * Transient failures (connection errors, 5xx) surface as their native
 * exceptions so the queue can retry them.
 */
class ScrapeException extends RuntimeException {}
