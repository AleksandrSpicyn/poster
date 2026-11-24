<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AdvanceVideoUpscaleJob;
use App\Repositories\VideoUpscaleOperationRepository;
use Illuminate\Console\Command;

class ReconcileVideoUpscales extends Command
{
    protected $signature = 'app:reconcile-video-upscales';

    protected $description = 'Resume outstanding video upscale operations';

    public function handle(VideoUpscaleOperationRepository $operations): int
    {
        foreach ($operations->due() as $id) {
            AdvanceVideoUpscaleJob::dispatch($id);
        }

        return self::SUCCESS;
    }
}
