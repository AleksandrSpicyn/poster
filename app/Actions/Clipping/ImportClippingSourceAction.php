<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Models\ClippingConnection;
use App\Models\Project;
use App\Repositories\OpusClipRepository;

final readonly class ImportClippingSourceAction
{
    public function __construct(private OpusClipRepository $clips) {}

    public function run(ClippingConnection $connection, int $preview): Project
    {
        return $this->clips->confirm($connection, $preview);
    }
}
