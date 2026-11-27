<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum GenerationOperationStatus: string
{
    use EnumTrait;

    case FRAME_REVIEW = 'frame_review';
    case PLANNED = 'planned';
    case SUBMITTING = 'submitting';
    case SUBMITTED = 'submitted';
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
    case SUBMISSION_UNKNOWN = 'submission_unknown';
    case CANCELLED = 'cancelled';
    case ABANDONED = 'abandoned';

    public function isActive(): bool
    {
        return in_array($this, self::active(), true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [
            self::FRAME_REVIEW,
            self::PLANNED,
            self::SUBMITTING,
            self::SUBMITTED,
            self::RUNNING,
            self::SUBMISSION_UNKNOWN,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::FRAME_REVIEW => 'Первый кадр на согласовании',
            self::PLANNED => 'В очереди',
            self::SUBMITTING => 'Отправляется',
            self::SUBMITTED => 'Отправлено',
            self::RUNNING => 'В работе',
            self::SUCCEEDED => 'Готово',
            self::FAILED => 'Ошибка',
            self::SUBMISSION_UNKNOWN => 'Нужна проверка',
            self::CANCELLED => 'Остановлено',
            self::ABANDONED => 'Попытка закрыта',
        };
    }
}
