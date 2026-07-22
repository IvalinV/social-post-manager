<?php

namespace App\Services\Publishing;

use RuntimeException;

/**
 * Thrown when a post cannot be published (not connected, API rejection, etc.).
 * The publish job records the message against the post and marks it Failed.
 */
class PublishException extends RuntimeException {}
