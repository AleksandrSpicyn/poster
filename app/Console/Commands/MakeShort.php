<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeShort extends Command
{
    protected $signature = 'app:make-short';

    protected $description = 'Queue project content using its standalone or series production mode';

    public function handle(): int
    {
        return $this->call('app:produce-content');
    }
}
