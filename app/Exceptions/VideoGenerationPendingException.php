<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Статус пока нельзя получить; повторяется только чтение существующей задачи. */
class VideoGenerationPendingException extends RuntimeException {}
