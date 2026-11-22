<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Classes\Clipping\ClippingReaders;
use Inertia\Response;

class CreateController
{
    /**
     * Доступные драйверы клиппинга
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingReaders $readers): Response
    {
        return inertia('Clipping/Create', ['drivers' => $readers->labels()]);
    }
}
