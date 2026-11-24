<?php

declare(strict_types=1);

namespace App\Data\Billing;

final readonly class ProductionQuoteData
{
    /**
     * @param array<int, int> $shots
     * @param array<string, mixed> $video
     */
    public function __construct(public int $seconds, public array $shots, public array $video, public ?int $revisionId) {}

    /** @return array{seconds: int, shots: array<int, int>, video: array<string, mixed>} */
    public function reservationSnapshot(): array
    {
        return [
            'seconds' => $this->seconds,
            'shots' => $this->shots,
            'video' => $this->video,
        ];
    }
}
