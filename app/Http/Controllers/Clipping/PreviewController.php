<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\PreviewClippingImportAction;
use App\Data\ClippingImportData;
use App\Models\ClippingConnection;
use Illuminate\Http\RedirectResponse;

class PreviewController
{
    /**
     * Подготовка предпросмотра импорта
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, ClippingImportData $data, PreviewClippingImportAction $action): RedirectResponse
    {
        $preview = $action->run($connection, $data);

        return to_route('clipping.preview.show', [$connection, $preview->id]);
    }
}
