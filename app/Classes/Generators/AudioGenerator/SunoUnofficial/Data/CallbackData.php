<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Data;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CallbackData extends Data
{
    /** @param Collection<int, TaskData> $tasks */
    public function __construct(
        #[Required]
        public string $callbackType,
        #[MapInputName('task_id'), Required]
        public string $taskId,
        #[MapInputName('data')]
        #[DataCollectionOf(TaskData::class), Required]
        public Collection $tasks,
    ) {}
}
