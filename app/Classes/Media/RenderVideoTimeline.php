<?php

declare(strict_types=1);

namespace App\Classes\Media;

use App\Data\Episodes\BuiltEpisodeVideoData;
use App\Data\Episodes\TimelineClipData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RenderVideoTimeline
{
    /** @param list<TimelineClipData> $clips */
    public function run(array $clips): BuiltEpisodeVideoData
    {
        $duration = array_sum(array_map(fn(TimelineClipData $clip): int => $clip->durationMs, $clips));

        if ($clips === [] || count($clips) > 100 || $duration > 600000) {
            throw new RuntimeException('The final timeline exceeds its duration or clip limit.');
        }

        $workspace = Storage::disk('local')->path('series-builds/' . Str::uuid());
        File::makeDirectory($workspace, 0700, true);

        try {
            $command = ['ffmpeg', '-v', 'error', '-nostdin', '-y', '-filter_complex_threads', '1'];
            $filters = [];
            $inputs = '';

            foreach ($clips as $index => $clip) {
                if (! is_file($clip->path) || $clip->durationMs < 100 || $clip->startMs < 0) {
                    throw new RuntimeException('The final timeline has an invalid source.');
                }

                $command = [...$command, '-protocol_whitelist', 'file,pipe', '-ss', sprintf('%.3f', $clip->startMs / 1000), '-i', $clip->path];
                $seconds = sprintf('%.3f', $clip->durationMs / 1000);
                $filters[] = "[$index:v]trim=duration=$seconds,setpts=PTS-STARTPTS,scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,setsar=1,fps=24,format=yuv420p[v$index]";
                $probeSource = Process::timeout(30)->run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration:stream=codec_type', '-of', 'json', $clip->path]);
                $metadata = json_decode($probeSource->output(), true, flags: JSON_THROW_ON_ERROR);
                $sourceDuration = data_get($metadata, 'format.duration');

                if (! $probeSource->successful() || ! is_numeric($sourceDuration) || $clip->startMs + $clip->durationMs > (float) $sourceDuration * 1000 + 50) {
                    throw new RuntimeException('A final timeline trim exceeds its source.');
                }

                $streams = data_get($metadata, 'streams');

                if (! is_array($streams)) {
                    throw new RuntimeException('A source has no stream metadata.');
                }

                $hasAudio = collect($streams)->contains(fn($stream): bool => data_get($stream, 'codec_type') === 'audio');
                $filters[] = $clip->mute || ! $hasAudio
                    ? "anullsrc=r=48000:cl=stereo,atrim=duration=$seconds,asetpts=PTS-STARTPTS[a$index]"
                    : "[$index:a]aresample=48000,aformat=channel_layouts=stereo,apad,atrim=duration=$seconds,asetpts=PTS-STARTPTS[a$index]";
                $inputs .= "[v$index][a$index]";
            }

            $filters[] = $inputs . 'concat=n=' . count($clips) . ':v=1:a=1[v][a]';
            $output = $workspace . '/video.mp4';
            $command = [...$command, '-filter_complex', implode(';', $filters), '-map', '[v]', '-map', '[a]',
                '-c:v', 'libx264', '-maxrate', '25M', '-bufsize', '50M', '-preset', 'veryfast', '-threads', '1', '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-ar', '48000', '-ac', '2', '-movflags', '+faststart', $output];
            $result = Process::timeout(3300)->run($command);

            if (! $result->successful() || ! is_file($output)) {
                throw new RuntimeException('The final video could not be rendered.');
            }

            $probe = Process::timeout(30)->run(['ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'json', $output]);
            $actual = data_get(json_decode($probe->output(), true, flags: JSON_THROW_ON_ERROR), 'format.duration');

            if (! $probe->successful() || ! is_numeric($actual) || abs((float) $actual * 1000 - $duration) > 100) {
                throw new RuntimeException('The final video duration does not match its timeline.');
            }

            return new BuiltEpisodeVideoData($output, (int) round((float) $actual * 1000), $workspace);
        } catch (Throwable $exception) {
            File::deleteDirectory($workspace);

            throw $exception;
        }
    }
}
