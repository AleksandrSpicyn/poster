<?php

namespace App\Jobs;

use App\Classes\Runway;
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

    /* TODO Inject через interface */
    public function handle(Runway $runway): void
    {
        $result = $runway->check($this->id);

        if(!$result){
            $this->release(10);
        }
    }
}
