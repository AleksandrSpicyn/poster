<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property-read string $canonical_id Provider ID selected for a batch import.
 * @property int $channel_id
 * @property string $provider_account_id
 * @property string $platform
 * @property string $external_id
 */
class ClippingAccountMapping extends Model
{
    protected $guarded = ['id'];
}
