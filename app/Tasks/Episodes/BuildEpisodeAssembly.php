<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\AssemblyClipData;
use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Data\Episodes\EpisodeAssemblyPlanData;
use App\Data\Episodes\VideoMetadataData;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
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

    public function run(EpisodeAssembly $assembly): BuiltEpisodeVideoData
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
                $this->normalize($shot, $sources->get($shot->mediaId), $workspace . '/' . $clip, $deadline);
                $clips[] = "file '" . $clip . "'";
            }

            File::put($workspace . '/concat.txt', implode("\n", $clips) . "\n");
            $output = $workspace . '/episode.mp4';
            $result = Process::path($workspace)->timeout($this->remainingSeconds($deadline))->run([
                'ffmpeg', '-v', 'error', '-nostdin', '-y', '-protocol_whitelist', 'file,pipe',
                '-f', 'concat', '-safe', '1', '-i', 'concat.txt', '-c:v', 'copy', '-c:a', 'aac', '-t', $this->seconds($duration), '-movflags', '+faststart', $output,
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

    private function normalize(AssemblyClipData $shot, ?Media $media, string $output, float $deadline): void
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
        $audioInput = $useAudio ? [] : ['-f', 'lavfi', '-i', 'anullsrc=r=48000:cl=stereo'];
        $seek = $shot->trimStartMs;
        $videoFilter = 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=24';
        $audioFilter = 'aresample=48000,apad';

        if ($shot->durationMs < 100) {
            // Decode the preceding frame as the selection may fall entirely between two source timestamps.
            $seek = max(0, $shot->trimStartMs - 1000);
            $offset = $this->seconds($shot->trimStartMs - $seek);
            $videoFilter = 'fps=1000:round=up,tpad=stop_mode=clone:stop_duration=0.05,trim=start=' . $offset . ',setpts=PTS-STARTPTS,scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,setsar=1';
            $audioFilter = 'atrim=start=' . $offset . ',asetpts=PTS-STARTPTS,' . $audioFilter;
        }

        $command = [
            'ffmpeg', '-v', 'error', '-nostdin', '-y', '-protocol_whitelist', 'file,pipe',
            '-ss', $this->seconds($seek), '-f', 'mov', '-i', $path, ...$audioInput,
            '-map', '0:v:0', '-map', $useAudio ? '0:a:0' : '1:a:0', '-t', $this->seconds($shot->durationMs),
            '-vf', $videoFilter, '-video_track_timescale', '24000',
            '-c:v', 'libx264', '-preset', 'veryfast', '-threads', '2', '-pix_fmt', 'yuv420p',
            '-af', $audioFilter, '-c:a', 'pcm_s16le', '-ar', '48000', '-ac', '2', '-movflags', '+faststart', $output,
        ];
        $result = Process::timeout($this->remainingSeconds($deadline))->run($command);

        if (! $result->successful() || ! is_file($output)) {
            throw new RuntimeException('Could not normalize a selected take.');
        }
    }

    private function probe(string $path): VideoMetadataData
    {
        $response = Process::timeout(30)->run([
            'ffprobe', '-v', 'error', '-protocol_whitelist', 'file,pipe', '-f', 'mov',
            '-show_entries', 'format=duration:stream=codec_type', '-of', 'json', $path,
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
