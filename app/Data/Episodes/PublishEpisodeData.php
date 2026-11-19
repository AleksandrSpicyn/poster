<?php

declare(strict_types=1);

namespace App\Data\Episodes;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class PublishEpisodeData extends Data
{
    /** @param list<int> $channel_ids */
    public function __construct(#[Min(1), Max(20)] public array $channel_ids, public bool $next_release_slot = false) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return ['channel_ids.*' => ['integer', 'min:1', 'distinct']];
    }
}
