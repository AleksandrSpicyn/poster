<?php

declare(strict_types=1);

namespace App\Classes\Generators\VideoGenerator;

use App\Classes\Generators\VideoGenerator\Runway\Runway;
use Illuminate\Container\Attributes\Bind;

/**
 * Отдельная DI-роль резервного видеогенератора с тем же протоколом polling.
 * Bind выбирает Runway при резервном запуске и последующей проверке.
 */
#[Bind(Runway::class)]
interface AlternativeVideoGenerator extends VideoGenerator {}
