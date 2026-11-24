<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Models\ClippingConnection;
use App\Models\Project;
use App\Repositories\OpusClipRepository;
use Illuminate\Support\Facades\DB;

final readonly class ImportClippingSourceAction
{
    public function __construct(private OpusClipRepository $clips) {}

    public function run(ClippingConnection $connection, int $preview): Project
    {
        return DB::transaction(fn(): Project => $this->clips->confirm($connection, $preview), attempts: 3);
    }
}
