<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator;

use App\Classes\Generators\VoiceSample;
use App\Data\Characters\VoicePreviewData;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class BytePlusSpeech
{
    private const int MAX_RESPONSE_BYTES = 3 * 1024 * 1024;

    /** @param array<string, string> $voices */
    public function __construct(
        #[Config('services.byteplus_speech.api_key')] private string $key,
        #[Config('services.byteplus_speech.voices')] private array $voices,
    ) {}

    /** @return array<string, string> */
    public function availableVoices(): array
    {
        return trim($this->key) === '' ? [] : $this->voices;
    }

    public function generate(VoicePreviewData $data): VoiceSample
    {
        if ($this->availableVoices() === []) {
            throw new HttpException(503, 'Генерация голоса BytePlus не настроена.');
        }

        if (! isset($this->voices[$data->voice_id])) {
            throw ValidationException::withMessages(['voice_id' => 'Выберите голос из списка.']);
        }

        try {
            $response = Http::withHeaders(['X-Api-Key' => $this->key])
                ->withOptions([
                    'decode_content' => false,
                    'progress' => static function (int $total, int $downloaded): void {
                        if (max($total, $downloaded) > self::MAX_RESPONSE_BYTES) {
                            throw new HttpException(502, 'Ответ BytePlus превысил допустимый размер.');
                        }
                    },
                ])
                ->connectTimeout(5)->timeout(35)->withoutRedirecting()
                ->post('https://voice.ap-southeast-1.bytepluses.com/api/v3/text-to-speech/' . rawurlencode($data->voice_id) . '/streaming', [
                    'text' => $data->text,
                    'model_id' => 'dola-seed-tts-3.0',
                    'language_code' => 'ru',
                    'output_format' => ['format' => 'pcm', 'sample_rate' => 24000],
                ]);
        } catch (ConnectionException) {
            throw new HttpException(503, 'Нет связи с BytePlus. Попробуйте позже.');
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw new HttpException(503, 'BytePlus отклонил доступ. Проверьте ключ Seed Speech и активацию модели.');
        }

        if (! $response->successful()) {
            throw new HttpException(502, 'BytePlus отклонил запрос голосовой пробы.');
        }

        $pcm = '';
        $ready = false;

        $body = $response->toPsrResponse()->getBody();

        if ($body->getSize() > self::MAX_RESPONSE_BYTES) {
            throw new HttpException(502, 'Ответ BytePlus превысил допустимый размер.');
        }

        $stream = $response->resource();

        while (($line = fgets($stream)) !== false) {
            if (trim($line) === '') {
                continue;
            }

            $event = json_decode($line, true);
            $type = is_array($event) ? ($event['event'] ?? null) : null;

            if ($type === 'ready' && ! $ready) {
                $ready = true;

                continue;
            }

            if ($ready && $type === 'audio' && is_string($event['audio'] ?? null)) {
                $bytes = base64_decode($event['audio'], true);

                if ($bytes === false || $bytes === '') {
                    break;
                }

                $pcm .= $bytes;

                if (strlen($pcm) > VoiceSample::MAX_PCM_BYTES) {
                    throw ValidationException::withMessages(['text' => 'Проба длиннее 30 секунд. Сократите реплику.']);
                }

                continue;
            }

            if ($ready && $type === 'final' && ($event['reason'] ?? null) === 'TTS_STREAM_FINAL_REASON_CLOSED' && ($event['is_final'] ?? false) === true && $pcm !== '') {
                return VoiceSample::fromPcm($pcm);
            }

            break;
        }

        throw new HttpException(502, 'BytePlus вернул неполную голосовую пробу.');
    }
}
