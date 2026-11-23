<?php

declare(strict_types=1);

namespace App\Http\Controllers\Clipping;

use App\Actions\Clipping\SaveClippingConnectionAction;
use App\Data\ClippingConnectionData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

class StoreController
{
    /**
     * Добавление подключения клиппинга
     *
     * @group Кабинет
     * @subgroup Клиппинг
     * @authenticated
     */
    public function __invoke(ClippingConnectionData $data, #[CurrentUser] User $owner, SaveClippingConnectionAction $action): RedirectResponse
    {
        $connection = $action->run($owner, $data);

        return to_route('clipping.edit', $connection);
    }
}
