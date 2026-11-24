<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Billing\GetBillingCatalogAction;
use Inertia\Response;

final class PricingController
{
    /**
     * Публичные тарифы студии.
     *
     * @unauthenticated
     * @group Публичные страницы
     */
    public function __invoke(GetBillingCatalogAction $catalog): Response
    {
        return inertia('Pricing', ['catalog' => $catalog->run()]);
    }
}
