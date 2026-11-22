<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Classes\Clipping\ArchivedClippingReader;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use Illuminate\Support\Collection;

final class ArchiveClippingReader implements ArchivedClippingReader
{
    public static function withKey(string $key): self
    {
        return new self();
    }

    public static function readsAccounts(): bool
    {
        return false;
    }

    public function sources(): iterable
    {
        yield new OpusSourceData('race');
    }

    public function sourcesIncludingArchives(): iterable
    {
        yield from $this->sources();
        yield new OpusSourceData('archived');
    }

    public function clips(string $projectId): Collection
    {
        return collect([new ImportedClipData('race-clip', null, null, null, [])]);
    }

    public function accounts(): array
    {
        return [];
    }
}
