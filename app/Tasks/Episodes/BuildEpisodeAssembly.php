<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\AssemblyClipData;
use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Data\Episodes\EpisodeAssemblyPlanData;
use App\Data\Episodes\VideoMetadataData;
use App\Enums\ShotCueKind;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\Series;
use App\Repositories\MediaRepository;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

readonly class BuildEpisodeAssembly
{
    public function __construct(
        #[Storage('local')] private FilesystemAdapter $disk,
        private MediaRepository $media,
    ) {}

    public function run(EpisodeAssembly $assembly, int $width = 720, int $height = 1280): BuiltEpisodeVideoData
    {
        $plan = EpisodeAssemblyPlanData::fromManifest($assembly->manifest);
        $duration = $plan->durationMs;

        $workspace = $this->disk->path('episode-builds/' . Str::uuid());
        File::makeDirectory($workspace, 0700, true);
        $clips = [];
        $deadline = microtime(true) + 3300;

        try {
            $sources = $this->media->byIds(array_column($plan->shots, 'mediaId'));

            foreach ($plan->shots as $index => $shot) {
                $clip = 'clip-' . $index . '.mov';
                $this->normalize($shot, $sources->get($shot->mediaId), $workspace . '/' . $clip, $deadline, $width, $height);

                if ($shot->cues !== []) {
                    $this->applyCues($shot, $workspace, $clip, $width, $height, $deadline);
                }

                $clips[] = "file '" . $clip . "'";
            }

            File::put($workspace . '/concat.txt', implode("\n", $clips) . "\n");
            $output = $workspace . '/episode.mp4';
            $result = Process::path($workspace)->timeout($this->remainingSeconds($deadline))->run([
                'ffmpeg',
                '-v',
                'error',
                '-nostdin',
                '-y',
                '-protocol_whitelist',
                'file,pipe',
                '-f',
                'concat',
                '-safe',
                '1',
                '-i',
                'concat.txt',
                '-c:v',
                'copy',
                '-c:a',
                'aac',
                '-t',
                $this->seconds($duration),
                '-movflags',
                '+faststart',
                $output,
            ]);

            if (! $result->successful() || ! is_file($output) || filesize($output) < 1) {
                throw new RuntimeException('Could not assemble the selected takes.');
            }

            $probe = $this->probe($output);

            if (abs($probe->durationMs - $duration) > 250 || ! $probe->hasAudio) {
                throw new RuntimeException('The assembled video failed its duration or audio check.');
            }

            return new BuiltEpisodeVideoData($output, $probe->durationMs, $workspace);
        } catch (Throwable $exception) {
            File::deleteDirectory($workspace);

            throw $exception;
        }
    }

    private function normalize(AssemblyClipData $shot, ?Media $media, string $output, float $deadline, int $width, int $height): void
    {
        if (! $media instanceof Media || $media->collection_name !== Prompt::SHOT_VIDEO) {
            throw new RuntimeException('A selected take is unavailable.');
        }

        $path = $media->getPath();

        if ($media->model_type !== new Prompt()->getMorphClass() || $media->model_id !== $shot->promptId
            || ! in_array($media->disk, ['local', 'public'], true) || ! is_file($path)
            || filesize($path) > 500000000 || hash_file('sha256', $path) !== $shot->checksum) {
            throw new RuntimeException('A selected take is unavailable or its contents changed.');
        }

        $probe = $this->probe($path);

        if ($shot->trimEndMs > $probe->durationMs + 50) {
            throw new RuntimeException('A trim exceeds the actual duration of a selected take.');
        }

        $useAudio = $probe->hasAudio && ! $shot->muteAudio;
        $audioInput = $useAudio ? [] : [
            '-f',
            'lavfi',
            '-i',
            'anullsrc=r=48000:cl=stereo',
        ];
        $seek = $shot->trimStartMs;
        $geometry = "scale={$width}:{$height}:force_original_aspect_ratio=decrease:force_divisible_by=2,pad={$width}:{$height}:(ow-iw)/2:(oh-ih)/2,setsar=1";
        $videoFilter = $geometry . ',fps=24';
        $audioFilter = 'aresample=48000,apad';

        if ($shot->durationMs < 100) {
            // Decode the preceding frame as the selection may fall entirely between two source timestamps.
            $seek = max(0, $shot->trimStartMs - 1000);
            $offset = $this->seconds($shot->trimStartMs - $seek);
            $videoFilter = 'fps=1000:round=up,tpad=stop_mode=clone:stop_duration=0.05,trim=start=' . $offset . ',setpts=PTS-STARTPTS,' . $geometry;
            $audioFilter = 'atrim=start=' . $offset . ',asetpts=PTS-STARTPTS,' . $audioFilter;
        }

        $command = [
            'ffmpeg',
            '-v',
            'error',
            '-nostdin',
            '-y',
            '-protocol_whitelist',
            'file,pipe',
            '-ss',
            $this->seconds($seek),
            '-f',
            'mov',
            '-i',
            $path,
            ...$audioInput,
            '-map',
            '0:v:0',
            '-map',
            $useAudio ? '0:a:0' : '1:a:0',
            '-t',
            $this->seconds($shot->durationMs),
            '-vf',
            $videoFilter,
            '-video_track_timescale',
            '24000',
            '-c:v',
            'libx264',
            '-preset',
            'veryfast',
            '-threads',
            '2',
            '-pix_fmt',
            'yuv420p',
            '-af',
            $audioFilter,
            '-c:a',
            'pcm_s16le',
            '-ar',
            '48000',
            '-ac',
            '2',
            '-movflags',
            '+faststart',
            $output,
        ];
        $result = Process::timeout($this->remainingSeconds($deadline))->run($command);

        if (! $result->successful() || ! is_file($output)) {
            throw new RuntimeException('Could not normalize a selected take.');
        }
    }

    private function applyCues(AssemblyClipData $shot, string $workspace, string $clip, int $width, int $height, float $deadline): void
    {
        $inputs = ['-i', $clip];
        $filters = [];
        $video = '0:v';
        $audioTracks = ['[0:a]'];
        $inputIndex = 0;

        foreach ($shot->cues as $index => $cue) {
            $start = max(0, $cue->start_ms - $shot->trimStartMs);
            $end = min($shot->durationMs, $cue->end_ms - $shot->trimStartMs);

            if ($end <= $start || ($cue->kind === ShotCueKind::SOUND && ($cue->asset_id === null || $shot->muteAudio))) {
                continue;
            }

            $enable = "enable='gte(t," . $this->seconds($start) . ')*lt(t,' . $this->seconds($end) . ")'";

            if ($cue->kind === ShotCueKind::TEXT) {
                $file = 'title-' . $index . '.svg';
                File::put($workspace . '/' . $file, $this->titleOverlay($cue->text, $width, $height));
                $inputs[] = '-i';
                $inputs[] = $file;
                $inputIndex++;
                $filters[] = "[$video][$inputIndex:v]overlay=$enable" . "[v$index]";
                $video = "v$index";

                continue;
            }

            if ($cue->asset_id === null) {
                throw new RuntimeException('Missing timed asset.');
            }

            $asset = $this->media->findOrFail($cue->asset_id);

            if ($asset->model_type !== new Series()->getMorphClass() || (int) $asset->model_id !== $shot->seriesId || $asset->disk !== 'local'
                || $asset->collection_name !== Series::ASSETS || $asset->getCustomProperty('kind') !== $cue->kind->value
                || ($cue->kind === ShotCueKind::INSERT && ! str_starts_with($asset->mime_type ?? '', 'image/'))
                || hash_file('sha256', $asset->getPath()) !== data_get($shot->assets, $cue->asset_id . '.checksum')) {
                throw new RuntimeException('The pinned timed asset is unavailable or changed.');
            }

            $inputs[] = '-i';
            $inputs[] = $asset->getPath();
            $inputIndex++;

            if ($cue->kind === ShotCueKind::INSERT) {
                $filters[] = "[$inputIndex:v]scale=$width:$height:force_original_aspect_ratio=decrease,pad=$width:$height:(ow-iw)/2:(oh-ih)/2,setsar=1[p$index]";
                $filters[] = "[$video][p$index]overlay=$enable" . "[v$index]";
                $video = "v$index";
            } else {
                $offset = $this->seconds(max(0, $shot->trimStartMs - $cue->start_ms));
                $length = $this->seconds($end - $start);
                $filters[] = "[$inputIndex:a]atrim=start=$offset:duration=$length,asetpts=PTS-STARTPTS,adelay=$start:all=1[a$index]";
                $audioTracks[] = "[a$index]";
            }
        }

        if ($filters === []) {
            return;
        }

        $audio = '0:a';

        if (count($audioTracks) > 1) {
            $filters[] = implode('', $audioTracks) . 'amix=inputs=' . count($audioTracks) . ':duration=first:normalize=0[aout]';
            $audio = '[aout]';
        }

        $out = 'cued-' . $clip;
        Process::path($workspace)->timeout($this->remainingSeconds($deadline))->run([
            'ffmpeg', '-v', 'error', '-nostdin', '-y', ...$inputs, '-filter_complex', implode(';', $filters),
            '-map', $video === '0:v' ? $video : "[$video]", '-map', $audio, '-c:v', 'libx264', '-pix_fmt', 'yuv420p',
            '-preset', 'veryfast', '-threads', '2', '-video_track_timescale', '24000', '-c:a', 'pcm_s16le', '-ar', '48000', '-ac', '2',
            '-t', $this->seconds($shot->durationMs), $out,
        ])->throw();

        if (! File::move($workspace . '/' . $out, $workspace . '/' . $clip)) {
            throw new RuntimeException('Could not save the clip with its timed cues.');
        }
    }

    private function titleOverlay(string $text, int $width, int $height): string
    {
        preg_match_all('/.{1,22}(?=\h|$)|.{1,22}/um', $text, $matches);
        $lines = array_values(array_filter(array_map(trim(...), $matches[0]), static fn(string $line): bool => $line !== ''));
        $count = max(1, count($lines));
        $font = max(1, (int) min($width / 16, $height / ($count * 2)));
        $spacing = (int) ceil($font * 1.3);
        $boxHeight = ($count + 1) * $spacing;
        $top = (int) min($height * 0.72, $height * 0.94 - $boxHeight);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height . '">'
            . '<rect x="3%" y="' . $top . '" width="94%" height="' . $boxHeight . '" rx="8" fill="black" fill-opacity="0.65"/>';

        foreach ($lines as $index => $line) {
            $baseline = $top + ($index + 1) * $spacing;
            $svg .= '<text x="50%" y="' . $baseline . '" text-anchor="middle" fill="white" font-family="DejaVu Sans,sans-serif" font-size="' . $font . '">' . htmlspecialchars($line, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</text>';
        }

        return $svg . '</svg>';
    }

    private function probe(string $path): VideoMetadataData
    {
        $response = Process::timeout(30)->run([
            'ffprobe',
            '-v',
            'error',
            '-protocol_whitelist',
            'file,pipe',
            '-f',
            'mov',
            '-show_entries',
            'format=duration:stream=codec_type',
            '-of',
            'json',
            $path,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('A video could not be decoded.');
        }

        $metadata = json_decode($response->output(), true, flags: JSON_THROW_ON_ERROR);
        $duration = data_get($metadata, 'format.duration');
        $streams = data_get($metadata, 'streams');

        if (! is_numeric($duration) || (float) $duration <= 0 || ! is_array($streams)) {
            throw new RuntimeException('A video has no valid duration or streams.');
        }

        $types = array_map(static fn(mixed $stream): mixed => is_array($stream) ? ($stream['codec_type'] ?? null) : null, $streams);

        if (! in_array('video', $types, true)) {
            throw new RuntimeException('A selected file contains no video stream.');
        }

        return new VideoMetadataData((int) round((float) $duration * 1000), in_array('audio', $types, true));
    }

    private function seconds(int $milliseconds): string
    {
        return number_format($milliseconds / 1000, 3, '.', '');
    }

    private function remainingSeconds(float $deadline): int
    {
        $remaining = (int) floor($deadline - microtime(true));

        if ($remaining < 1) {
            throw new RuntimeException('The assembly exceeded its processing time budget.');
        }

        return $remaining;
    }
}
