<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\ResumeClippingSourceAction;
use App\Models\ClippingConnection;
use Illuminate\Http\RedirectResponse;

class ResumeController
{
    /**
     * Возобновление обновления источника после восстановления доступа
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, int $source, ResumeClippingSourceAction $action): RedirectResponse
    {
        $action->run($connection, $source);

        return to_route('clipping.history');
    }
}
