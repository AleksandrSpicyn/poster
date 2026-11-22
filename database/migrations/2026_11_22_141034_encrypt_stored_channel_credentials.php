<?php

declare(strict_types=1);

use App\Models\Channel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->convert(encrypt: true);
    }

    public function down(): void
    {
        $this->convert(encrypt: false);
    }

    private function convert(bool $encrypt): void
    {
        foreach (Channel::query()->whereNotNull('credentials')->select('id')->lazyById() as $record) {
            DB::transaction(static function () use ($record, $encrypt): void {
                $channel = Channel::query()->lockForUpdate()->find($record->id);

                if ($channel === null || $channel->credentials === null) {
                    return;
                }

                $credentials = $channel->credentials;

                if ($encrypt) {
                    $channel->credentials = $credentials;
                    $value = $channel->getAttributes()['credentials'];
                } else {
                    $value = json_encode($credentials, JSON_THROW_ON_ERROR);
                }

                DB::table('channels')->where('id', $channel->id)->update(['credentials' => $value]);
            });
        }
    }
};
