<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\UpdateClippingKeyAction;
use App\Data\ClippingKeyData;
use App\Models\ClippingConnection;
use Illuminate\Http\RedirectResponse;

class UpdateKeyController
{
    /**
     * Обновление ключа подключения
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnection $connection, ClippingKeyData $data, UpdateClippingKeyAction $action): RedirectResponse
    {
        $action->run($connection, $data);

        return to_route('clipping.edit', $connection);
    }
}
