<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

class TaskData extends Data
{
    public function __construct(
        public string $id,
        #[MapInputName('audio_url')]
        public ?string $audioUrl = null,
    ) {}
}
