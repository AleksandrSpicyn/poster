<?php

declare(strict_types=1);

namespace App\Classes\Generators;

use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

final readonly class VoiceSample
{
    public const int MAX_PCM_BYTES = 30 * self::SAMPLE_RATE * 2;

    private const int SAMPLE_RATE = 24000;

    private function __construct(public string $wav, public int $durationMs) {}

    public static function fromUpload(string $contents, ?string $mime, int $minimumMs = 2000): self
    {
        $format = match ($mime) {
            'audio/wav', 'audio/wave', 'audio/x-wav', 'audio/vnd.wave' => 'wav',
            'audio/mpeg', 'audio/mp3' => 'mp3',
            default => throw ValidationException::withMessages(['file' => 'Загрузите образец речи в WAV или MP3.']),
        };

        return self::decode($contents, $format, '30.01', $minimumMs);
    }

    public static function excerpt(string $wav, int $durationMs): self
    {
        return self::decode($wav, 'wav', $durationMs . 'ms', 2000);
    }

    private static function decode(string $contents, string $format, string $duration, int $minimumMs): self
    {
        // Decode a bounded clip. Measuring the decoded samples also handles VBR MP3.
        $result = Process::timeout(20)->input($contents)->run([
            'ffmpeg', '-nostdin', '-v', 'error', '-xerror', '-protocol_whitelist', 'pipe', '-f', $format, '-i', 'pipe:0',
            '-map', '0:a:0', '-t', $duration, '-ac', '1', '-ar', (string) self::SAMPLE_RATE, '-f', 's16le', 'pipe:1',
        ]);
        $pcm = $result->output();

        if (! $result->successful()) {
            throw ValidationException::withMessages(['file' => 'Не удалось прочитать аудиозапись.']);
        }

        return self::fromPcm($pcm, $minimumMs);
    }

    public static function fromPcm(string $pcm, int $minimumMs = 2000): self
    {
        $size = strlen($pcm);
        $bytesPerSecond = self::SAMPLE_RATE * 2;

        if ($size % 2 !== 0 || $size * 1000 < $minimumMs * $bytesPerSecond || $size > self::MAX_PCM_BYTES) {
            throw ValidationException::withMessages(['file' => 'Нужна читаемая аудиозапись от ' . $minimumMs . ' мс до 30 секунд.']);
        }

        $wav = 'RIFF' . pack('V', 36 + $size) . 'WAVEfmt ' . pack('VvvVVvv', 16, 1, 1, self::SAMPLE_RATE, $bytesPerSecond, 2, 16)
            . 'data' . pack('V', $size) . $pcm;

        return new self($wav, (int) ceil($size * 1000 / $bytesPerSecond));
    }
}
