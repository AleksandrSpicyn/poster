<?php

declare(strict_types=1);

namespace App\Tasks\Episodes;

use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
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
    public function __construct(#[Storage('local')] private FilesystemAdapter $disk) {}

    public function run(EpisodeAssembly $assembly): BuiltEpisodeVideoData
    {
        $shots = $this->shots($assembly->manifest);
        $duration = array_sum(array_column($shots, 'duration_ms'));

        if ($duration < 100 || $duration > 600000) {
            throw new RuntimeException('The assembly duration is outside the supported range.');
        }

        $workspace = $this->disk->path('episode-builds/' . Str::uuid());
        File::makeDirectory($workspace, 0700, true);
        $clips = [];
        $deadline = microtime(true) + 3300;

        try {
            foreach ($shots as $index => $shot) {
                $media = Media::query()->where('collection_name', Prompt::SHOT_VIDEO)->findOrFail($shot['media_id']);
                $path = $media->getPath();

                if ($media->model_type !== new Prompt()->getMorphClass() || $media->model_id !== $shot['prompt_id']
                    || ! in_array($media->disk, ['local', 'public'], true) || ! is_file($path)
                    || filesize($path) > 500000000 || hash_file('sha256', $path) !== $shot['checksum']) {
                    throw new RuntimeException('A selected take is unavailable or its contents changed.');
                }

                $probe = $this->probe($path);

                if ($shot['trim_end_ms'] > $probe['duration_ms'] + 50) {
                    throw new RuntimeException('A trim exceeds the actual duration of a selected take.');
                }

                $clip = 'clip-' . $index . '.mp4';
                $command = ['ffmpeg', '-v', 'error', '-nostdin', '-y', '-protocol_whitelist', 'file,pipe',
                    '-ss', $this->seconds($shot['trim_start_ms']), '-f', 'mov', '-i', $path];

                $useAudio = $probe['has_audio'] && ! $shot['mute_audio'];

                if (! $useAudio) {
                    $command[] = '-f';
                    $command[] = 'lavfi';
                    $command[] = '-i';
                    $command[] = 'anullsrc=r=48000:cl=stereo';
                }

                $command[] = '-map';
                $command[] = '0:v:0';
                $command[] = '-map';
                $command[] = $useAudio ? '0:a:0' : '1:a:0';
                $command[] = '-t';
                $command[] = $this->seconds($shot['duration_ms']);
                $command[] = '-vf';
                $command[] = 'scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=24';
                $command[] = '-c:v';
                $command[] = 'libx264';
                $command[] = '-preset';
                $command[] = 'veryfast';
                $command[] = '-threads';
                $command[] = '2';
                $command[] = '-pix_fmt';
                $command[] = 'yuv420p';
                $command[] = '-af';
                $command[] = 'aresample=48000,apad';
                $command[] = '-c:a';
                $command[] = 'aac';
                $command[] = '-ar';
                $command[] = '48000';
                $command[] = '-ac';
                $command[] = '2';
                $command[] = '-movflags';
                $command[] = '+faststart';
                $command[] = $workspace . '/' . $clip;
                $result = Process::timeout($this->remainingSeconds($deadline))->run($command);

                if (! $result->successful() || ! is_file($workspace . '/' . $clip)) {
                    throw new RuntimeException('Could not normalize a selected take.');
                }

                $clips[] = "file '" . $clip . "'";
            }

            File::put($workspace . '/concat.txt', implode("\n", $clips) . "\n");
            $output = $workspace . '/episode.mp4';
            $result = Process::path($workspace)->timeout($this->remainingSeconds($deadline))->run([
                'ffmpeg', '-v', 'error', '-nostdin', '-y', '-protocol_whitelist', 'file,pipe',
                '-f', 'concat', '-safe', '1', '-i', 'concat.txt', '-c', 'copy', '-movflags', '+faststart', $output,
            ]);

            if (! $result->successful() || ! is_file($output) || filesize($output) < 1) {
                throw new RuntimeException('Could not assemble the selected takes.');
            }

            $probe = $this->probe($output);

            if (abs($probe['duration_ms'] - $duration) > 250 || ! $probe['has_audio']) {
                throw new RuntimeException('The assembled video failed its duration or audio check.');
            }

            return new BuiltEpisodeVideoData($output, $probe['duration_ms'], $workspace);
        } catch (Throwable $exception) {
            File::deleteDirectory($workspace);

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array{media_id: int, prompt_id: int, checksum: string, trim_start_ms: int, trim_end_ms: int, duration_ms: int, mute_audio: bool}>
     */
    private function shots(array $manifest): array
    {
        $shots = $manifest['shots'] ?? null;

        if (! is_array($shots) || ! array_is_list($shots) || $shots === [] || count($shots) > 100) {
            throw new RuntimeException('The assembly manifest is invalid.');
        }

        $result = [];

        foreach ($shots as $shot) {
            if (! is_array($shot) || ! is_int($shot['media_id'] ?? null) || ! is_int($shot['prompt_id'] ?? null)
                || ! is_string($shot['checksum'] ?? null) || ! is_int($shot['trim_start_ms'] ?? null)
                || ! is_int($shot['trim_end_ms'] ?? null) || ! is_int($shot['duration_ms'] ?? null)
                || (array_key_exists('mute_audio', $shot) && ! is_bool($shot['mute_audio']))
                || $shot['trim_start_ms'] < 0 || $shot['duration_ms'] < 100
                || $shot['trim_end_ms'] - $shot['trim_start_ms'] !== $shot['duration_ms']) {
                throw new RuntimeException('The assembly manifest contains an invalid trim or media reference.');
            }

            $result[] = ['media_id' => $shot['media_id'], 'prompt_id' => $shot['prompt_id'],
                'checksum' => $shot['checksum'], 'trim_start_ms' => $shot['trim_start_ms'],
                'trim_end_ms' => $shot['trim_end_ms'], 'duration_ms' => $shot['duration_ms'], 'mute_audio' => $shot['mute_audio'] ?? false];
        }

        return $result;
    }

    /** @return array{duration_ms: int, has_audio: bool} */
    private function probe(string $path): array
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

        return ['duration_ms' => (int) round((float) $duration * 1000), 'has_audio' => in_array('audio', $types, true)];
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
