<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Classes\Generators\VideoGenerator\Runway\Runway;
use Illuminate\Container\Attributes\Bind;

#[Bind(Runway::class)]
interface AlternativeVideoGenerator extends VideoGenerator {}
