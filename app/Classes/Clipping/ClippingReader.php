<?php

declare(strict_types=1);

namespace App\Classes\Clipping;

use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use Illuminate\Support\Collection;

interface ClippingReader
{
    public static function withKey(string $key): self;

    public static function readsAccounts(): bool;

    /** @return iterable<int, OpusSourceData> */
    public function sources(): iterable;

    /** @return Collection<int, ImportedClipData> */
    public function clips(string $projectId): Collection;

    /** @return list<array{id: string, platform: string, external_id: string, name: string}> */
    public function accounts(): array;
}
