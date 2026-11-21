<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Contains only application-authored messages safe for the channel operator. */
final class ChannelAuthorizationException extends RuntimeException {}
