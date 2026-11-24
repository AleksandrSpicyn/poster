<?php

declare(strict_types=1);

namespace App\Actions\Publications;

use App\Models\Channel;
use App\Repositories\MediaRepository;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

readonly class GetTiktokPreviewAction
{
    public function __construct(private MediaRepository $media) {}

    public function run(Channel $channel, Media $media): string
    {
        $path = $this->media->publishingVideo($channel, $media->id)->getPath();
        abort_unless(is_file($path), 404);

        return $path;
    }
}
