<?php

declare(strict_types=1);

namespace App\Enums;

use App\Traits\EnumTrait;

enum ProductionStatus: string
{
    use EnumTrait;

    case QUEUED = 'queued';
    case RUNNING = 'running';
    case WAITING = 'waiting';
    case NEEDS_ATTENTION = 'needs_attention';
    case COMPLETED = 'completed';
    case STOPPED = 'stopped';

    public function label(): string
    {
        return match ($this) {
            self::QUEUED => 'В очереди',
            self::RUNNING => 'В работе',
            self::WAITING => 'Ожидание',
            self::NEEDS_ATTENTION => 'Требует внимания',
            self::COMPLETED => 'Готово',
            self::STOPPED => 'Остановлено',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::QUEUED,
            self::RUNNING,
            self::WAITING,
        ], true);
    }

    public function blocksNextRun(): bool
    {
        return $this->isActive() || $this === self::NEEDS_ATTENTION;
    }

    /** @return list<string> */
    public static function blockingValues(): array
    {
        return array_values(collect(self::cases())
            ->filter(fn(self $status): bool => $status->blocksNextRun())
            ->map(fn(self $status): string => $status->value)
            ->values()
            ->all());
    }
}
