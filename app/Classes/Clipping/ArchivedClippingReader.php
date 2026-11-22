<?php

declare(strict_types=1);

namespace App\Classes\Clipping;

use App\Data\OpusClip\OpusSourceData;

interface ArchivedClippingReader extends ClippingReader
{
    /** @return iterable<int, OpusSourceData> */
    public function sourcesIncludingArchives(): iterable;
}
