<?php

declare(strict_types=1);

namespace App\Http\Controllers\Billing;

use App\Actions\Billing\QuoteGenerationAction;
use App\Data\Billing\GenerationQuoteData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class QuoteController
{
    /**
     * Рассчитать стоимость генерации в секундах
     *
     * @group Кабинет
     * @subgroup Подписка
     * @authenticated
     * @response 200
     * @response 422
     */
    public function __invoke(#[CurrentUser] User $owner, GenerationQuoteData $data, QuoteGenerationAction $action): JsonResponse
    {
        return response()->json($action->run($owner, $data));
    }
}
