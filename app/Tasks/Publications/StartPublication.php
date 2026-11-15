<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Classes\Publishers\PublisherFactory;
use App\Classes\Publishers\StagedPublisher;
use App\Data\PublicationSubmissionData;
use App\Data\PublishVideoData;
use App\Models\Channel;

readonly class StartPublication
{
    public function __construct(private PublisherFactory $publishers) {}

    public function run(Channel $channel, PublishVideoData $data): PublicationSubmissionData
    {
        $publisher = $this->publishers->make($channel, $data);

        if ($publisher instanceof StagedPublisher) {
            return new PublicationSubmissionData(true, $publisher->createContainer());
        }

        return new PublicationSubmissionData($publisher->publish());
    }
}
