<?php

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class CallbackData extends Data
{
    public function __construct(
        public string $callbackType,
        #[MapInputName('task_id')]
        public string $taskId,
        #[MapInputName('data')]
        #[DataCollectionOf(TaskData::class)]
        public Collection $tasks,
    ) {
    }
}
