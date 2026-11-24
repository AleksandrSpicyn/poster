<?php

declare(strict_types=1);

namespace App\Actions\Upscale;

use App\Classes\Media\Upscale\PollUnavailable;
use App\Classes\Media\Upscale\UpscaleRejected;
use App\Classes\Media\Upscale\VideoUpscalerFactory;
use App\Classes\Story\Snapshot;
use App\Data\Upscale\UpscaleReceiptData;
use App\Data\Upscale\UpscaleRequestData;
use App\Enums\UpscaleExport;
use App\Enums\UpscaleOperationStatus as Status;
use App\Enums\UpscaleQueueStatus;
use App\Jobs\AdvanceVideoUpscaleJob;
use App\Models\EpisodeAssembly;
use App\Models\Prompt;
use App\Models\SeriesAssembly;
use App\Models\VideoUpscaleOperation;
use App\Repositories\VideoUpscaleOperationRepository;
use App\Tasks\Episodes\BuildEpisodeAssembly;
use App\Tasks\Upscale\UpscaleMedia;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

final readonly class AdvanceVideoUpscaleAction
{
    public function __construct(
        private VideoUpscaleOperationRepository $operations,
        private VideoUpscalerFactory $drivers,
        private BuildEpisodeAssembly $build,
        private UpscaleMedia $media,
    ) {}

    public function run(int $id): void
    {
        $operation = $this->operations->findOrFail($id);

        if (isset($operation->source_snapshot['part_count'])) {
            $this->advanceMultipartExport($operation);

            return;
        }

        if ($operation->status === Status::SUBMITTING) {
            // The previous worker released its lease without recording the paid POST outcome.
            $this->operations->mark($operation, Status::SUBMISSION_UNKNOWN);

            return;
        }

        if ($operation->status === Status::PENDING) {
            $this->submit($operation, $operation->source());

            return;
        }

        if (! in_array($operation->status, [Status::SUBMITTED, Status::PROCESSING, Status::DOWNLOADING], true)) {
            return;
        }

        $this->poll($operation, $operation->source());
    }

    private function advanceMultipartExport(VideoUpscaleOperation $operation): void
    {
        if (! in_array($operation->status, [Status::PENDING, Status::PROCESSING], true)) {
            return;
        }

        $parts = $this->operations->parts($operation);

        if ($parts->count() !== $operation->source_snapshot['part_count'] || $parts->contains(fn(VideoUpscaleOperation $part): bool => in_array($part->status, [Status::FAILED, Status::CANCELLED], true))) {
            $this->operations->mark($operation, Status::FAILED);

            return;
        }

        if ($parts->every(fn(VideoUpscaleOperation $part): bool => $part->status === Status::READY)) {
            $file = $this->media->verifiedFile($operation->source(), $operation, EpisodeAssembly::UPSCALED)
                ?? $this->media->join($operation, $parts);
            $this->operations->completeWithResult($operation, $file, $this->checksum($file));

            return;
        }

        $this->operations->mark($operation, Status::PROCESSING);
        AdvanceVideoUpscaleJob::dispatch($operation->id)->delay(now()->addSeconds(30));
    }

    private function poll(VideoUpscaleOperation $operation, EpisodeAssembly|SeriesAssembly|Prompt $assembly): void
    {
        $result = $this->media->verifiedFile($assembly, $operation, EpisodeAssembly::UPSCALED);

        if ($result instanceof Media) {
            $this->operations->completeWithResult($operation, $result, $this->checksum($result));

            return;
        }

        $receipt = $operation->receipt ?? throw new RuntimeException('Missing upscale receipt.');

        try {
            $status = $this->drivers->make($operation->provider)->check(new UpscaleReceiptData($receipt['id'], $receipt['statusUrl'], $receipt['resultUrl']));

            if ($status->status === UpscaleQueueStatus::READY && $status->videoUrl !== null) {
                $this->operations->mark($operation, Status::DOWNLOADING);
                $media = $this->media->output($assembly, $operation, $status->videoUrl, $this->export($operation));
                $this->operations->completeWithResult($operation, $media, $this->checksum($media));

                return;
            }

            $this->operations->mark($operation, $status->status === UpscaleQueueStatus::PROCESSING ? Status::PROCESSING : Status::SUBMITTED);
        } catch (UpscaleRejected $exception) {
            $this->operations->mark($operation, Status::FAILED, $exception::class);

            return;
        } catch (PollUnavailable $exception) {
            $this->operations->mark($operation, $operation->status, $exception::class);
        } catch (Throwable $exception) {
            $this->operations->mark($operation, $operation->status, $exception::class);

            return;
        }

        AdvanceVideoUpscaleJob::dispatch($operation->id)->delay(now()->addSeconds(30));
    }

    private function submit(VideoUpscaleOperation $operation, EpisodeAssembly|SeriesAssembly|Prompt $assembly): void
    {
        try {
            if ($assembly instanceof EpisodeAssembly && ! isset($operation->source_snapshot['media_id']) && Snapshot::hash($assembly->manifest) !== Snapshot::hash($operation->source_snapshot)) {
                $this->operations->mark($operation, Status::CANCELLED);

                return;
            }

            $driver = $this->drivers->make($operation->provider);
            $profile = $driver->capabilities();

            if ($profile->model !== $operation->profile_snapshot['model'] || $profile->sourceWidth !== $operation->profile_snapshot['source_width']
                || $profile->sourceHeight !== $operation->profile_snapshot['source_height'] || $profile->fps !== $operation->profile_snapshot['fps']) {
                throw new InvalidArgumentException('Saved export profile is unavailable.');
            }

            $input = $this->media->verifiedFile($assembly, $operation, EpisodeAssembly::UPSCALE_INPUT)
                ?? $this->media->input($assembly, $operation, $assembly instanceof EpisodeAssembly && ! isset($operation->source_snapshot['media_id'])
                    ? $this->build->run($assembly, $profile->sourceWidth, $profile->sourceHeight) : $this->media->renderedInput($assembly, $operation));
            $this->operations->recordInput($operation, $input, $this->checksum($input));
            $url = URL::temporarySignedRoute('upscales.input', now()->addDay(), ['operation' => $operation->id, 'checksum' => $operation->source_sha256]);
            $request = new UpscaleRequestData($url, $operation->durationMs(), $this->export($operation));
            $profile->assertSupports($request);
        } catch (Throwable $exception) {
            $this->operations->mark($operation, Status::FAILED, $exception::class);

            return;
        }

        if (! $this->operations->claim($operation, $assembly)) {
            return;
        }

        try {
            $receipt = $driver->submit($request);
        } catch (UpscaleRejected|InvalidArgumentException $exception) {
            $this->operations->mark($operation, Status::FAILED, $exception::class);

            return;
        } catch (Throwable $exception) {
            $this->operations->mark($operation, Status::SUBMISSION_UNKNOWN, $exception::class);

            return;
        }

        $this->operations->storeReceipt($operation, $receipt);
        AdvanceVideoUpscaleJob::dispatch($operation->id)->delay(now()->addSeconds(30));
    }

    private function export(VideoUpscaleOperation $operation): UpscaleExport
    {
        $value = $operation->profile_snapshot['export'];

        return is_string($value) ? UpscaleExport::from($value) : throw new InvalidArgumentException('Invalid export profile.');
    }

    private function checksum(Media $media): string
    {
        $checksum = $media->getCustomProperty('checksum');

        if (! is_string($checksum)) {
            throw new RuntimeException('Missing upscale checksum.');
        }

        return $checksum;
    }
}
