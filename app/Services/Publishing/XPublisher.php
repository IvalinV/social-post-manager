<?php

namespace App\Services\Publishing;

use App\Contracts\Publisher;
use App\Enums\Platform;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Services\Social\SocialTokenManager;
use App\Services\Social\TokenRefreshException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Publishes a post to X via the v2 API (POST /2/tweets) using an OAuth 2.0 user
 * token, refreshing it first if it has expired.
 */
class XPublisher implements Publisher
{
    private const string TWEETS_ENDPOINT = 'https://api.x.com/2/tweets';

    public function __construct(private readonly SocialTokenManager $tokens) {}

    public function platform(): Platform
    {
        return Platform::X;
    }

    public function publish(Post $post): PublishResult
    {
        $account = SocialAccount::where('platform', Platform::X)->first();

        if ($account === null) {
            throw new PublishException('X is not connected. Connect the account first.');
        }

        try {
            $token = $this->tokens->freshAccessToken($account);
        } catch (TokenRefreshException $e) {
            throw new PublishException($e->getMessage(), previous: $e);
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->post(self::TWEETS_ENDPOINT, ['text' => (string) $post->body]);
        } catch (Throwable $e) {
            throw new PublishException('Could not reach X to publish the post.', previous: $e);
        }

        if ($response->failed()) {
            throw new PublishException('X rejected the post: '.$this->errorDetail($response));
        }

        $id = (string) $response->json('data.id');

        if ($id === '') {
            throw new PublishException('X did not return a post id.');
        }

        $handle = $account->account_handle ?: 'i';

        return new PublishResult($id, "https://x.com/{$handle}/status/{$id}");
    }

    private function errorDetail(Response $response): string
    {
        $detail = $response->json('detail')
            ?? $response->json('title')
            ?? $response->json('errors.0.message');

        return Str::limit(
            is_string($detail) ? $detail : "HTTP {$response->status()}",
            300,
        );
    }
}
