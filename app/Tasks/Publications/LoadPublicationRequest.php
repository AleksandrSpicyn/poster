<?php

declare(strict_types=1);

namespace App\Tasks\Publications;

use App\Models\PublicationRequest;
use App\Repositories\PublicationRequestRepository;

readonly class LoadPublicationRequest
{
    public function __construct(private PublicationRequestRepository $publications) {}

    public function run(int $id): PublicationRequest
    {
        return $this->publications->findOrFail($id);
    }
}
