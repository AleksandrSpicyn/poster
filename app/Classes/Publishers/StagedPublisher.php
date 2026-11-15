<?php

declare(strict_types=1);

namespace App\Classes\Publishers;

interface StagedPublisher extends Publisher
{
    public function createContainer(): string;

    public function publishContainer(string $containerId): void;
}
