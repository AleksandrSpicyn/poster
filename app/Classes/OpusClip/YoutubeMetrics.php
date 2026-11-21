<?php

declare(strict_types=1);

namespace App\Classes\OpusClip;

use App\Support\Integrations\Payload;
use Generator;
use Illuminate\Http\Client\PendingRequest;
use UnexpectedValueException;

final readonly class YoutubeMetrics
{
    /**
     * @param list<string> $references
     * @return Generator<string, array<string, int|float>>
     */
    public function read(PendingRequest $http, string $account, array $references): Generator
    {
        foreach (array_chunk($references, 100) as $ids) {
            $report = $http->withoutRedirecting()->timeout(30)->get('https://youtubeanalytics.googleapis.com/v2/reports', [
                'ids' => 'channel==' . $account, 'startDate' => '2005-01-01', 'endDate' => now('America/Los_Angeles')->toDateString(),
                'metrics' => 'views,engagedViews,averageViewDuration,averageViewPercentage,shares', 'dimensions' => 'video',
                'filters' => 'video==' . implode(',', $ids), 'maxResults' => count($ids),
            ])->throw();
            $headers = $report->json('columnHeaders');

            if (! is_array($headers)) {
                throw new UnexpectedValueException('Missing YouTube report headers.');
            }

            $columns = array_map(static fn($header): string => Payload::string(data_get($header, 'name'), 'column name'), $headers);
            $rows = $report->json('rows', []);

            if (! is_array($rows)) {
                throw new UnexpectedValueException('Invalid YouTube report.');
            }

            foreach ($rows as $row) {
                if (! is_array($row) || count($row) !== count($columns)) {
                    throw new UnexpectedValueException('Invalid YouTube report row.');
                }

                $metrics = array_combine($columns, $row);
                $id = Payload::string($metrics['video'] ?? null, 'video');
                unset($metrics['video']);

                if (in_array($id, $ids, true)) {
                    yield $id => array_filter($metrics, static fn($value): bool => is_int($value) || is_float($value));
                }
            }
        }
    }
}
