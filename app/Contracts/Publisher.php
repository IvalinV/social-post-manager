<?php

namespace App\Contracts;

use App\Enums\Platform;
use App\Models\Post;
use App\Services\Publishing\PublishResult;

/**
 * Publishes a single post to a specific social platform. Implementations are
 * resolved per platform (see Phase 5): XPublisher is live, LinkedInPublisher
 * is stubbed until its app is approved.
 */
interface Publisher
{
    public function platform(): Platform;

    /**
     * Publish the post and return the platform's identifiers for it.
     * Implementations should throw on failure (never return a partial result).
     */
    public function publish(Post $post): PublishResult;
}
