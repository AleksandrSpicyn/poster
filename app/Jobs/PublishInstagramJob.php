<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Publishers\Instagram\Instagram;
use App\Classes\Publishers\PublisherFactory;
use App\Classes\Publishers\StagedPublisher;
use App\Data\PublishVideoData;
use App\Models\Channel;
use App\Models\Prompt;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/** Выполняет отложенный второй этап Instagram после создания Reels-контейнера. */
class PublishInstagramJob implements ShouldQueue
{
    use Queueable {
        __unserialize as private restoreQueueState;
    }

    private Channel $channel;

    private Prompt|PublishVideoData $source;

    public function __construct(Instagram $publisher, private readonly string $containerId)
    {
        $this->channel = $publisher->channel();
        $this->source = $publisher->source();
    }

    /**
     * Старый payload содержит Publisher с прежним набором DI-полей.
     * Из него извлекается только контекст; зависимости создаёт текущая фабрика.
     *
     * @param array<string, mixed> $values
     */
    public function __unserialize(array $values): void
    {
        $this->restoreQueueState($values);
        $publisher = $values["\0" . self::class . "\0publisher"] ?? null;

        if ($publisher instanceof Instagram) {
            $this->channel = $publisher->channel();
            $this->source = $publisher->source();
        }
    }

    /** @throws ConnectionException */
    public function handle(PublisherFactory $factory): void
    {
        $publisher = $factory->make($this->channel, $this->source);

        if (! $publisher instanceof StagedPublisher) {
            throw new RuntimeException('The queued publication requires a staged publisher.');
        }

        $publisher->publishContainer($this->containerId);
    }
}
