<?php

namespace App\Services\Publishing;

use App\Contracts\Publisher;
use App\Enums\Platform;
use App\Models\Post;

/**
 * Stub until the LinkedIn app and "Share on LinkedIn" product are approved.
 * The platform-generic design lets this slot in as a real implementation later
 * without touching the job, factory, or UI.
 */
class LinkedInPublisher implements Publisher
{
    public function platform(): Platform
    {
        return Platform::LinkedIn;
    }

    public function publish(Post $post): PublishResult
    {
        throw new PublishException('LinkedIn publishing is not available yet.');
    }
}
