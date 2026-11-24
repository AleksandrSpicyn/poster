<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\BillingProduct;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class BillingRenewalReminder extends Notification
{
    public function __construct(public readonly string $due, public readonly BillingProduct $product, public readonly bool $automatic) {}

    /** @return list<string> */
    public function via(User $user): array
    {
        return $user->hasVerifiedEmail() ? ['mail'] : [];
    }

    public function shouldSend(User $user): bool
    {
        return $user->hasVerifiedEmail();
    }

    public function toMail(): MailMessage
    {
        $date = CarbonImmutable::parse($this->due, 'UTC')->setTimezone('Europe/Moscow')->format('d.m.Y в H:i');

        return new MailMessage()->subject('Заканчивается оплаченный месяц Poster')->line('Конец оплаченного периода — ' . $date . ' МСК.')
            ->line(sprintf('%s · %d ₽ за месяц.', $this->product->label(), intdiv($this->product->amountKopecks(), 100)))
            ->line($this->automatic ? 'Запланировано списание с сохранённой карты. Управлять продлением можно в кабинете.' : 'Следующий месяц можно оплатить в кабинете вручную.')
            ->action('Открыть подписку', route('billing.show'));
    }
}
