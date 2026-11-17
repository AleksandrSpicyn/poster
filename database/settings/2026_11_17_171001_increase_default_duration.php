<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->update('general.duration', static fn(int $duration): int => $duration === 10 ? 15 : $duration);
    }
};
