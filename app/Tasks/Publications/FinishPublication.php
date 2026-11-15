<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Classes\Publishers\PublisherFactory;
use App\Classes\Publishers\StagedPublisher;
use App\Data\PublishVideoData;
use App\Models\Channel;
use RuntimeException;

readonly class FinishPublication
{
    public function __construct(private PublisherFactory $publishers) {}

    public function run(Channel $channel, PublishVideoData $data, string $containerId): void
    {
        $publisher = $this->publishers->make($channel, $data);

        if (! $publisher instanceof StagedPublisher) {
            throw new RuntimeException('The selected channel does not support prepared publication.');
        }

        $publisher->publishContainer($containerId);
    }
}
