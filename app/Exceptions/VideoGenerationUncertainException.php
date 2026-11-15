<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Провайдер мог принять запрос: автоматический резервный запуск небезопасен. */
class VideoGenerationUncertainException extends RuntimeException {}
