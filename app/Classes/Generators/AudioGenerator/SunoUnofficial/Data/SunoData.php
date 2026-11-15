<?php

declare(strict_types=1);

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Data;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SunoData extends Data
{
    public function __construct(
        #[Required, IntegerType]
        public int $code,
        #[Required]
        public string $msg,
        #[Required]
        public CallbackData $data,
    ) {}
}
