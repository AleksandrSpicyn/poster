<?php

declare(strict_types=1);

namespace App\Classes\Media\Upscale;

use App\Data\Upscale\UpscaleReceiptData;
use App\Data\Upscale\UpscaleRequestData;
use App\Data\Upscale\UpscaleStatusData;
use Illuminate\Http\Client\ConnectionException;

abstract readonly class AbstractAsyncVideoUpscaler implements VideoUpscaler
{
    final public function submit(UpscaleRequestData $request): UpscaleReceiptData
    {
        $this->capabilities()->assertSupports($request);

        try {
            return $this->send($request);
        } catch (ConnectionException) {
            throw new SubmissionUnknown('Upscale submission could not be confirmed.');
        }
    }

    final public function check(UpscaleReceiptData $receipt): UpscaleStatusData
    {
        try {
            return $this->poll($receipt);
        } catch (ConnectionException) {
            throw new PollUnavailable('Upscale queue is temporarily unavailable.');
        }
    }

    abstract protected function send(UpscaleRequestData $request): UpscaleReceiptData;

    abstract protected function poll(UpscaleReceiptData $receipt): UpscaleStatusData;
}
