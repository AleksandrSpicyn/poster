<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class TaskData extends Data
{
    public function __construct(
        #[Required]
        public string $id,
        #[MapInputName('audio_url')]
        public ?string $audioUrl = null,
    ) {}
}
