<?php

declare(strict_types=1);

namespace App\Data;

final readonly class VerifiedPublicationData
{
    public function __construct(public string $externalId, public string $url) {}
}
