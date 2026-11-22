<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Classes\Clipping\ClippingReader;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use Illuminate\Support\Collection;
use LogicException;

final class ClipsOnlyReader implements ClippingReader
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
        yield new OpusSourceData('sample', 'Тестовый проект');
    }

    public function clips(string $projectId): Collection
    {
        return collect([new ImportedClipData('clip', 'Тестовый клип', null, null, [])]);
    }

    public function accounts(): array
    {
        throw new LogicException('This driver cannot read accounts.');
    }
}
