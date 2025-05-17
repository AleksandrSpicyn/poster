<?php

namespace App\Jobs;

use App\Classes\Generators\VideoGenerator\VideoGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/* TODO Добавить в Pipelene */
class CheckVideoCreation implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 2;

    public function __construct(private readonly string $id)
    {
    }

    public function handle(VideoGenerator $videoGenerator): void
    {
        $result = $videoGenerator->check($this->id);

        if (!$result) {
            $this->release(10);
        }
    }
}
