<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Enums\ChannelType;
use App\Support\Integrations\Payload;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use UnexpectedValueException;

final readonly class InstagramMetrics
{
    /**
     * @param list<string> $references
     * @return Generator<string, array<string, int|float>>
     */
    public function read(PendingRequest $http, string $account, array $references): Generator
    {
        $http->withoutRedirecting()->timeout(30)->baseUrl('https://graph.instagram.com/' . config()->string('services.instagram.version'));
        $remaining = array_fill_keys($references, true);
        $seen = [];
        $params = ['fields' => 'id,permalink', 'limit' => 100];

        do {
            $page = $http->get(rawurlencode($account) . '/media', $params)->throw();

            foreach ($page->collect('data') as $media) {
                $ref = VideoReference::fromUrl(ChannelType::INSTAGRAM, Payload::string(data_get($media, 'permalink'), 'permalink'));

                if ($ref === null || ! isset($remaining[$ref])) {
                    continue;
                }

                $id = Payload::string(data_get($media, 'id'), 'id');
                $metrics = $this->insights($http, $id);

                if ($metrics !== []) {
                    yield $ref => $metrics;
                }

                unset($remaining[$ref]);
            }

            if ($remaining === [] || ! $page->json('paging.next')) {
                return;
            }

            $cursor = Payload::string($page->json('paging.cursors.after'), 'paging.cursors.after');

            if (isset($seen[$cursor])) {
                throw new UnexpectedValueException('Repeated Instagram media cursor.');
            }

            $params['after'] = $cursor;
            $seen[$cursor] = true;
        } while (true);
    }

    /** @return array<string, int|float> */
    private function insights(PendingRequest $http, string $id): array
    {
        $response = $http->get(rawurlencode($id) . '/insights', ['metric' => 'views,reach,ig_reels_avg_watch_time,shares,saved'])->throw();
        $metrics = [];

        foreach ($response->collect('data') as $metric) {
            $value = data_get($metric, 'values.0.value');

            if (! is_int($value) && ! is_float($value)) {
                continue;
            }

            $name = Payload::string(data_get($metric, 'name'), 'name');

            if ($name === 'ig_reels_avg_watch_time') {
                $metrics['averageViewDuration'] = $value / 1000;
            } else {
                $metrics[$name] = $value;
            }
        }

        return $metrics;
    }
}
