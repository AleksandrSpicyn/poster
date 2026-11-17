<?php

declare(strict_types=1);

namespace App\Tasks\Series;

use App\Classes\Media\RenderVideoTimeline;
use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Data\Episodes\TimelineClipData;
use App\Data\Series\SeriesAssemblyManifestData;
use App\Enums\SeriesAudioMode;
use App\Models\EpisodeAssembly;
use App\Models\SeriesAssembly;
use App\Repositories\MediaRepository;
use RuntimeException;

readonly class BuildSeriesAssembly
{
    public function __construct(private MediaRepository $media, private RenderVideoTimeline $render) {}

    public function run(SeriesAssembly $assembly): BuiltEpisodeVideoData
    {
        $clips = [];

        foreach (SeriesAssemblyManifestData::fromSnapshot($assembly->manifest)->parts as $part) {
            $media = $this->media->findOrFail($part['media_id']);

            if ($media->model_type !== new EpisodeAssembly()->getMorphClass() || $media->model_id !== $part['assembly_id']
                || $media->collection_name !== EpisodeAssembly::VIDEO || $media->disk !== 'local' || ! is_file($media->getPath())
                || filesize($media->getPath()) > 500000000 || hash_file('sha256', $media->getPath()) !== $part['checksum']) {
                throw new RuntimeException('An accepted part is unavailable or its checksum changed.');
            }

            $clips[] = new TimelineClipData($media->getPath(), 0, $part['duration_ms'], ($assembly->series->specification['audio_mode'] ?? null) === SeriesAudioMode::SILENT->value);
        }

        return $this->render->run($clips);
    }
}
