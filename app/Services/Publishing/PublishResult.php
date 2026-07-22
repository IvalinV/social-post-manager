<?php

namespace App\Services\Publishing;

/**
 * The outcome of publishing a post to a platform: the platform's own id for the
 * created post and a canonical URL to view it.
 */
readonly class PublishResult
{
    public function __construct(
        public string $platformPostId,
        public string $platformUrl,
    ) {}
}
