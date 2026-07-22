<?php

namespace App\Services\Publishing;

use App\Contracts\Publisher;
use App\Enums\Platform;

/**
 * Resolves the Publisher implementation for a platform.
 */
class PublisherFactory
{
    public function for(Platform $platform): Publisher
    {
        return match ($platform) {
            Platform::X => app(XPublisher::class),
            Platform::LinkedIn => app(LinkedInPublisher::class),
        };
    }
}
