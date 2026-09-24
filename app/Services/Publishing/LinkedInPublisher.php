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
 * Publishes text-only posts through LinkedIn's current Posts API.
 */
class LinkedInPublisher implements Publisher
{
    private const string POSTS_ENDPOINT = 'https://api.linkedin.com/rest/posts';

    private const string API_VERSION = '202609';

    public function __construct(private readonly SocialTokenManager $tokens) {}

    public function platform(): Platform
    {
        return Platform::LinkedIn;
    }

    public function publish(Post $post): PublishResult
    {
        $account = SocialAccount::where('platform', Platform::LinkedIn)->first();

        if ($account === null) {
            throw new PublishException('LinkedIn is not connected. Connect the account first.');
        }

        if (blank($account->account_urn)) {
            throw new PublishException('LinkedIn account details are incomplete. Reconnect the account.');
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
                ->withHeaders([
                    'Linkedin-Version' => self::API_VERSION,
                    'X-Restli-Protocol-Version' => '2.0.0',
                ])
                ->post(self::POSTS_ENDPOINT, [
                    'author' => $account->account_urn,
                    'commentary' => (string) $post->body,
                    'visibility' => 'PUBLIC',
                    'distribution' => [
                        'feedDistribution' => 'MAIN_FEED',
                        'targetEntities' => [],
                        'thirdPartyDistributionChannels' => [],
                    ],
                    'lifecycleState' => 'PUBLISHED',
                    'isReshareDisabledByAuthor' => false,
                ]);
        } catch (Throwable $e) {
            throw new PublishException('Could not reach LinkedIn to publish the post.', previous: $e);
        }

        if ($response->failed()) {
            throw new PublishException('LinkedIn rejected the post: '.$this->errorDetail($response));
        }

        $id = (string) $response->header('x-restli-id');

        if ($id === '') {
            throw new PublishException('LinkedIn did not return a post id.');
        }

        return new PublishResult($id, 'https://www.linkedin.com/feed/update/'.$id);
    }

    private function errorDetail(Response $response): string
    {
        $detail = $response->json('message')
            ?? $response->json('error_description')
            ?? $response->json('serviceErrorCode');

        return Str::limit(
            is_string($detail) || is_numeric($detail) ? (string) $detail : "HTTP {$response->status()}",
            300,
        );
    }
}
