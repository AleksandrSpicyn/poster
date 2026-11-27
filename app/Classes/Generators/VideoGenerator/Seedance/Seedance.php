<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator\Seedance;

use App\Classes\Generators\VideoGenerator\AsyncVideoGenerator;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\GenerationStatus;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Model;
use App\Classes\Generators\VideoGenerator\Seedance\Enums\Resolution;
use App\Data\VideoCapabilitiesData;
use App\Data\VideoGenerationRequestData;
use App\Data\VideoResultData;
use App\Data\VideoUsageData;
use App\Enums\VideoAudioSource;
use App\Enums\VideoInputMode;
use App\Enums\VideoReceiptStatus;
use App\Exceptions\MalformedVideoResultException;
use App\Exceptions\VideoGeneratingException;
use App\Support\Integrations\Payload;
use Illuminate\Container\Attributes\Config;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/** Асинхронный BytePlus ModelArk: кадры, референсы и продолжение видео. */
readonly class Seedance extends AsyncVideoGenerator
{
    public function __construct(
        #[Config('services.seedance.api_url')] private string $baseUrl,
        #[Config('services.seedance.api_key')] private string $token,
        #[Config('services.seedance.model')] private string $model,
        #[Config('services.seedance.resolution')] private string $resolution,
        #[Storage('public')] private FilesystemAdapter $disk,
    ) {}

    public function generate(VideoGenerationRequestData $request): string
    {
        $this->capabilities($request->model)->assertSupports($request);

        if ($request->mode !== VideoInputMode::REFERENCES && $request->aspectRatio !== 'adaptive') {
            throw new InvalidArgumentException('Seedance frame and extension modes require an adaptive aspect ratio.');
        }

        $model = Model::from($request->model);
        $content = [['type' => 'text', 'text' => $request->prompt]];

        if ($request->mode === VideoInputMode::FIRST_FRAME) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $this->imageSource($request->firstFrameUrl ?? '')],
                'role' => 'first_frame',
            ];

            if ($request->lastFrameUrl !== null) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $this->imageSource($request->lastFrameUrl)],
                    'role' => 'last_frame',
                ];
            }
        } else {
            foreach ($request->referenceImageUrls as $url) {
                $content[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => $this->imageSource($url)],
                    'role' => 'reference_image',
                ];
            }

            foreach ($request->audioReferences as $audio) {
                $content[] = ['type' => 'audio_url', 'audio_url' => ['url' => $audio->url], 'role' => 'reference_audio'];
            }

            if ($request->mode === VideoInputMode::EXTEND) {
                $content[] = [
                    'type' => 'video_url',
                    'video_url' => ['url' => $request->sourceVideoUrl],
                    'role' => 'reference_video',
                ];
            }
        }

        $payload = [
            'model' => $model->value,
            'content' => $content,
            'resolution' => $request->resolution,
            'ratio' => $request->aspectRatio,
            'duration' => $request->duration,
            'generate_audio' => $request->generateAudio,
            'return_last_frame' => $request->returnLastFrame,
            'watermark' => false,
            'execution_expires_after' => 3600,
        ];

        if ($model->supportsExtension() && $request->mode !== VideoInputMode::FIRST_FRAME) {
            $payload['omni_reference_task_type'] = $request->mode === VideoInputMode::EXTEND ? 'extend' : 'reference';
        }

        return $this->submit(fn(): Response => $this->request()->post('contents/generations/tasks', $payload)->throw(), 'id');
    }

    public function capabilities(?string $model = null): VideoCapabilitiesData
    {
        $model = Model::from($model ?? $this->model);
        $modes = [VideoInputMode::FIRST_FRAME, VideoInputMode::REFERENCES];

        if ($model->supportsExtension()) {
            $modes[] = VideoInputMode::EXTEND;
        }

        return new VideoCapabilitiesData(
            model: $model->value,
            modes: $modes,
            minDuration: 4,
            maxDuration: $model->maxDuration(),
            resolutions: array_values(collect(Resolution::cases())->filter($model->supports(...))->map(fn(Resolution $resolution): string => $resolution->value)->all()),
            defaultResolution: $this->resolution,
            aspectRatios: [
                'adaptive',
                '9:16',
                '16:9',
                '1:1',
                '4:3',
                '3:4',
                '21:9',
            ],
            maxReferenceImages: $model->maxReferenceImages(),
            supportsLastFrame: true,
            supportsReturnLastFrame: true,
            supportsAudioGeneration: true,
            maxAudioReferences: match ($model) {
                Model::V2_5 => 10,
                Model::V2_5_FLASH => 5,
                default => 3,
            },
            maxReferenceAudioMs: $model->supportsExtension() ? 30000 : 15000,
        );
    }

    protected function poll(string $id): ?VideoResultData
    {
        $response = $this->request()
            ->get('contents/generations/tasks/' . rawurlencode($id))
            ->throw();

        $status = GenerationStatus::from(Payload::string($response->json('status'), 'status'));

        if ($status === GenerationStatus::QUEUED || $status === GenerationStatus::RUNNING) {
            return null;
        }

        $tokens = $response->json('usage.completion_tokens');
        $resolution = $response->json('resolution');
        $duration = $response->json('duration');
        $model = $response->json('model');
        $usage = new VideoUsageData(
            VideoReceiptStatus::from($status->value),
            is_int($tokens) && $tokens >= 0 ? $tokens : null,
            is_string($resolution) ? $resolution : null,
            is_string($model) ? $model : null,
            is_int($duration) && $duration > 0 ? $duration : null,
        );

        if ($status !== GenerationStatus::SUCCEEDED) {
            throw VideoGeneratingException::withUsage('Seedance task ended with status: ' . $status->value, $usage);
        }

        $hasAudio = $response->json('generate_audio');

        if (! is_bool($hasAudio)) {
            throw VideoGeneratingException::withUsage('Seedance returned no audio-generation metadata.', $usage);
        }

        try {
            $url = Payload::string($response->json('content.video_url'), 'content.video_url');
            $lastFrame = $response->json('content.last_frame_url');

            if ($duration !== null && (! is_int($duration) || $duration < 1)) {
                throw new UnexpectedValueException('Seedance returned an invalid duration.');
            }

            return new VideoResultData(
                $url,
                $hasAudio ? $url : null,
                $lastFrame === null ? null : Payload::string($lastFrame, 'content.last_frame_url'),
                $duration,
                $model === null ? null : Payload::string($model, 'model'),
                $usage,
            );
        } catch (UnexpectedValueException $exception) {
            throw new MalformedVideoResultException($exception, $usage);
        }
    }

    public function audioSource(): VideoAudioSource
    {
        return VideoAudioSource::EMBEDDED;
    }

    public function pollingAttempts(): int
    {
        return 61;
    }

    private function request(): PendingRequest
    {
        if (trim($this->token) === '') {
            throw new RuntimeException('Seedance API key is not configured.');
        }

        return Http::baseUrl(rtrim($this->baseUrl, '/') . '/')
            ->withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(60);
    }

    /** Передаёт сохранённый кадр напрямую: localhost и закрытые сети недоступны BytePlus. */
    private function imageSource(string $imageUrl): string
    {
        $prefix = rtrim($this->disk->url('generated-images'), '/') . '/';

        if (! str_starts_with($imageUrl, $prefix)) {
            return $imageUrl;
        }

        $fileName = substr($imageUrl, strlen($prefix));

        if (preg_match('/\A[a-zA-Z0-9_-]+\.(png|jpe?g|webp)\z/', $fileName, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid generated first-frame path.');
        }

        $path = 'generated-images/' . $fileName;
        $size = $this->disk->size($path);

        if ($size < 1 || $size >= 30_000_000) {
            throw new InvalidArgumentException('Seedance first frame must be smaller than 30 MB and nonempty.');
        }

        $mime = match ($matches[1]) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };

        return 'data:' . $mime . ';base64,' . base64_encode(Payload::string($this->disk->get($path), 'first_frame'));
    }
}
