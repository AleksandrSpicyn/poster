<?php

declare(strict_types=1);

namespace App\Classes\Billing;

final class FiscalProfile
{
    public static function offerUrl(): ?string
    {
        $url = config('billing.offer_url');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;
    }

    public static function liveReady(): bool
    {
        return config()->boolean('billing.live_sales') && self::configured() !== null && self::offerUrl() !== null;
    }

    /** @return array{vat: int, subject: string, mode: string, tax_system: ?int}|null */
    public static function configured(): ?array
    {
        $vat = filter_var(config('billing.fiscal.vat'), FILTER_VALIDATE_INT);
        $tax = config('billing.fiscal.tax_system') ?: null;
        $subject = config('billing.fiscal.subject');
        $mode = config('billing.fiscal.mode');

        if (! is_int($vat) || $vat < 1 || ! is_string($subject) || $subject === '' || ! in_array($mode, ['full_payment', 'full_prepayment'], true)) {
            return null;
        }

        if ($tax !== null && ! is_int($tax = filter_var($tax, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 6]]))) {
            return null;
        }

        return ['vat' => $vat, 'subject' => $subject, 'mode' => $mode, 'tax_system' => $tax];
    }
}
