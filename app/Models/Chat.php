<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Chat extends Model
{
    use Notifiable;

    protected $fillable = [
        'chat_id',
    ];

    public function routeNotificationForTelegram(): string
    {
        return $this->chat_id;
    }
}
