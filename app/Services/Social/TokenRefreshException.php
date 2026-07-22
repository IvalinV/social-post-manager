<?php

namespace App\Services\Social;

use RuntimeException;

/**
 * Thrown when an access token has expired and cannot be refreshed (no refresh
 * token, or the provider rejected the refresh). The account must be reconnected.
 */
class TokenRefreshException extends RuntimeException {}
