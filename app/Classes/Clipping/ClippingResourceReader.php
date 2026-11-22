<?php

declare(strict_types=1);

namespace App\Classes\Clipping;

use App\Data\OpusClip\ImportedClipData;
use Illuminate\Support\Collection;

interface ClippingResourceReader extends ClippingReader
{
    /** @return Collection<int, ImportedClipData> */
    public function clipMetadata(string $projectId): Collection;

    /** @return Collection<int, ImportedClipData> */
    public function clipPublications(string $projectId): Collection;
}
