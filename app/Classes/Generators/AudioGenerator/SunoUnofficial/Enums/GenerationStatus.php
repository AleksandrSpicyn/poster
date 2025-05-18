<?php

namespace App\Classes\Generators\AudioGenerator\SunoUnofficial\Enums;

enum GenerationStatus: string
{
    case COMPLETE = 'complete';
    case FIRST = 'first';
    case TEXT = 'text';
}
