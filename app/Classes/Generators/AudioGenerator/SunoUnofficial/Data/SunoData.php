<?php

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Data;

use Spatie\LaravelData\Data;

class SunoData extends Data
{
    public function __construct(
        public string $code,
        public string $msg,
        public CallbackData $data,
    ) {
    }
}
