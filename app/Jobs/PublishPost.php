<?php

namespace App\Jobs;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Publishing\PublisherFactory;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Publishes a single post to its platform.
 *
 * Double-post safety: publishing is NOT idempotent (X has no idempotency key),
 * so this job never auto-retries ($tries = 1). It atomically claims the post by
 * flipping Draft/Failed → Publishing in a single conditional UPDATE, so even
 * with multiple queue workers only one job can proceed; the losers no-op.
 * ShouldBeUnique additionally collapses duplicate dispatches at the source.
 *
 * Residual risk: if X actually creates the tweet but the response is lost
 * (network timeout after the write), the post is marked Failed and a manual
 * "Retry publish" WILL post a duplicate — there is no idempotency key to
 * prevent this. The retry confirmation warns the operator to check X first.
 */
class PublishPost implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * No automatic retries — a retry could post a duplicate.
     */
    public int $tries = 1;

    public function __construct(public Post $post) {}

    public function uniqueId(): string
    {
        return (string) $this->post->getKey();
    }

    public function handle(PublisherFactory $factory): void
    {
        // Atomically claim the post: a single conditional UPDATE flips
        // Draft/Failed → Publishing. Only one worker can win the row, so this is
        // safe regardless of how many queue workers run. Losers affect 0 rows.
        $claimed = Post::whereKey($this->post->getKey())
            ->whereIn('status', [PostStatus::Draft->value, PostStatus::Failed->value])
            ->update([
                'status' => PostStatus::Publishing->value,
                'error_message' => null,
            ]);

        if ($claimed === 0) {
            return; // already Publishing/Published, or claimed by another worker
        }

        $this->post->refresh();

        try {
            $result = $factory->for($this->post->platform)->publish($this->post);
        } catch (Throwable $e) {
            $this->post->update([
                'status' => PostStatus::Failed,
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);

            return;
        }

        $this->post->update([
            'status' => PostStatus::Published,
            'platform_post_id' => $result->platformPostId,
            'platform_url' => $result->platformUrl,
            'published_at' => now(),
            'error_message' => null,
        ]);
    }
}
