<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Classes\Publishers\PublisherFactory;
use App\Classes\Publishers\VerifiablePublisher;
use App\Data\PublishVideoData;
use App\Data\VerifiedPublicationData;
use App\Exceptions\PublicationVerificationException;
use App\Models\Channel;

readonly class VerifyPublication
{
    public function __construct(private PublisherFactory $publishers) {}

    public function run(Channel $channel, PublishVideoData $video, string $externalId): VerifiedPublicationData
    {
        $publisher = $this->publishers->make($channel, $video);

        if (! $publisher instanceof VerifiablePublisher) {
            throw new PublicationVerificationException('Площадка не поддерживает проверку принадлежности видео.');
        }

        return $publisher->verifyPublication($externalId);
    }
}
