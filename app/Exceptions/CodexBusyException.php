<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Общая авторизация занята; новый CLI-процесс ещё не запускался. */
class CodexBusyException extends RuntimeException {}
