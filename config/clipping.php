<?php

declare(strict_types=1);

use App\Classes\OpusClip\OpusClipClient;
use App\Enums\ClippingDriver;

return [
    'drivers' => [
        ClippingDriver::OPUS_CLIP->value => ['label' => ClippingDriver::OPUS_CLIP->label(), 'reader' => OpusClipClient::class],
    ],
];
