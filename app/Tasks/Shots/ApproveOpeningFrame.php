<?php

declare(strict_types=1);

namespace App\Tasks\Shots;

use App\Classes\Billing\PilotShotQuote;
use App\Enums\GenerationOperationStage;
use App\Enums\GenerationOperationStatus;
use App\Models\GenerationOperation;
use App\Models\Prompt;
use App\Models\Series;
use App\Repositories\GenerationOperationRepository;
use App\Repositories\GenerationReservationRepository;
use App\Repositories\PromptRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

readonly class ApproveOpeningFrame
{
    public function __construct(private PromptRepository $prompts, private GenerationOperationRepository $operations, private GenerationReservationRepository $usage) {}

    public function run(Series $series, Prompt $take, int $operationId, ?int $assetId): ?GenerationOperation
    {
        $take = $this->prompts->findLockedOrFail($take->id);
        $operation = $this->operations->lockForPromptOperation($take, $operationId);

        if ($operation->stage !== GenerationOperationStage::VIDEO || $operation->cancel_requested_at !== null) {
            throw ValidationException::withMessages(['frame' => 'Этот дубль нельзя запустить.']);
        }

        $result = $operation->result_snapshot ?? [];

        if ($operation->status !== GenerationOperationStatus::FRAME_REVIEW) {
            if (isset($result['frame_approved_at']) && ($result['approved_frame_id'] ?? null) === $assetId) {
                return null;
            }

            throw ValidationException::withMessages(['frame' => 'Этот кадр уже прошёл согласование.']);
        }

        $image = $assetId === null ? $take->getFirstMedia(Prompt::SHOT_IMAGE) : $series->getMedia(Series::ASSETS)->firstWhere('id', $assetId);

        if ($image === null || ! str_starts_with($image->mime_type ?? '', 'image/') || File::size($image->getPath()) >= 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['frame' => 'Для первого кадра нужно изображение этого сериала до 5 МБ.']);
        }

        $bytes = File::get($image->getPath());

        if ($this->usage->quotedSeconds($take->id) !== null) {
            PilotShotQuote::assertVerticalFrame($bytes);
        }

        $this->operations->update($operation, ['status' => GenerationOperationStatus::PLANNED, 'next_check_at' => now(),
            'result_snapshot' => [...$result, 'approved_frame_id' => $assetId, 'approved_frame_sha256' => hash('sha256', $bytes), 'frame_approved_at' => now()->toISOString()]]);

        return $operation;
    }
}
