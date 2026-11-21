<?php

declare(strict_types=1);

namespace App\Actions\OpusClip;

use App\Classes\OpusClip\OpusClipClient;
use App\Data\OpusClip\ImportedClipData;
use App\Data\OpusClip\OpusSourceData;
use App\Repositories\OpusClipRepository;
use Illuminate\Support\Collection;

final readonly class SyncOpusClipsAction
{
    public function __construct(private OpusClipClient $client, private OpusClipRepository $clips) {}

    /** @return Collection<int, ImportedClipData> */
    public function run(OpusSourceData $source, bool $preview): Collection
    {
        $items = $this->client->clips($source->id);

        if (! $preview) {
            $this->clips->import($source, $items);
        }

        return $items;
    }
}
