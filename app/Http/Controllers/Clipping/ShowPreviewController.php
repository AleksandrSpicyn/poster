<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\GetClippingPreviewAction;
use App\Models\ClippingConnection;
use Inertia\Response;

class ShowPreviewController
{
    /**
     * Сопоставление и состав импорта
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, int $preview, GetClippingPreviewAction $action): Response
    {
        return inertia('Clipping/Preview', $action->run($connection, $preview));
    }
}
