<?php

declare(strict_types=1);

namespace App\Data\Publications;

use App\Data\PublishVideoData;
use App\Models\Channel;
use App\Models\PublicationRequest;
use Spatie\LaravelData\Data;

class PreparedPublicationData extends Data
{
    public function __construct(
        public Channel $channel,
        public PublishVideoData $video,
        public PublicationRequest $request,
    ) {}
}
