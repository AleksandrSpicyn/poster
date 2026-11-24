<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Classes\Media\Upscale\VideoUpscalerFactory;
use App\Data\Upscale\UpscaleReceiptData;
use App\Enums\UpscaleOperationStatus;
use App\Jobs\AdvanceVideoUpscaleJob;
use App\Repositories\VideoUpscaleOperationRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResolveVideoUpscale extends Command
{
    protected $signature = 'app:resolve-video-upscale {operation} {--request-id=} {--result-url=} {--actual-usd=}';

    protected $description = 'Attach a verified fal receipt or record a confirmed USD charge';

    public function handle(VideoUpscaleOperationRepository $operations, VideoUpscalerFactory $drivers): int
    {
        $id = (int) $this->argument('operation');
        $operation = $operations->findOrFail($id);
        $amount = $this->option('actual-usd');
        $requestId = $this->option('request-id');
        $result = $this->option('result-url');

        if ($amount !== null && (! is_numeric($amount) || ! preg_match('/^\d{1,8}(?:\.\d{1,8})?$/D', $amount))) {
            $this->error('Enter the confirmed USD charge with at most eight decimal places.');

            return self::FAILURE;
        }

        $receipt = null;

        if ($requestId !== null || $result !== null) {
            if ($operation->status !== UpscaleOperationStatus::SUBMISSION_UNKNOWN
                || ! is_string($requestId) || ! is_string($result) || ! preg_match('/^[a-zA-Z0-9-]+$/D', $requestId)
                || ! preg_match('~^https://queue\.fal\.run/topaz/[a-zA-Z0-9/_-]+/requests/' . preg_quote($requestId, '~') . '$~D', $result)) {
                $this->error('Check the operation status and original fal receipt.');

                return self::FAILURE;
            }

            $receipt = new UpscaleReceiptData($requestId, $result . '/status', $result);
            $drivers->make($operation->provider)->check($receipt);
        }

        if ($amount === null && ! $receipt instanceof UpscaleReceiptData) {
            $this->error('Provide a verified receipt or confirmed charge.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($operations, $id, $receipt, $amount): int {
            $locked = $operations->findLockedOrFail($id);

            if ($receipt instanceof UpscaleReceiptData) {
                if ($locked->status !== UpscaleOperationStatus::SUBMISSION_UNKNOWN) {
                    return self::FAILURE;
                }

                $operations->storeReceipt($locked, $receipt);
                AdvanceVideoUpscaleJob::dispatch($id)->afterCommit();
            }

            if (is_numeric($amount)) {
                $locked->actual_cost_e8 = (int) bcmul($amount, '100000000', 0);
                $operations->save($locked);
            }

            return self::SUCCESS;
        });
    }
}
