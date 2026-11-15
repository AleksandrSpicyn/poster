<?php

declare(strict_types=1);

namespace App\Data;

final readonly class PublicationSubmissionData
{
    public function __construct(public bool $accepted, public ?string $containerId = null) {}
}
