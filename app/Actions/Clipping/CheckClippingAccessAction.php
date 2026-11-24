<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Classes\Clipping\ClippingResourceReader;
use App\Data\ClippingAccessData;
use App\Enums\ClippingAccessStatus as Status;
use App\Exceptions\ClippingProviderException;
use App\Models\ClippingConnection;
use App\Repositories\ClippingConnectionRepository;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class CheckClippingAccessAction
{
    public function __construct(private ClippingReaders $readers, private ClippingConnectionRepository $connections) {}

    /** @return array{projects: Status, clips: Status, accounts: Status, metrics: Status, publications: Status, account_count: int|null, titles: list<string>, sample: string|null, source: string|null, project_id: int|null} */
    public function run(ClippingConnection $connection, ClippingAccessData $data): array
    {
        $result = $this->read($connection, $data);
        $this->connections->recordAccess($connection, $result);

        return $result;
    }

    /** @return array{projects: Status, clips: Status, accounts: Status, metrics: Status, publications: Status, account_count: int|null, titles: list<string>, sample: string|null, source: string|null, project_id: int|null} */
    private function read(ClippingConnection $connection, ClippingAccessData $data): array
    {
        $targets = $this->connections->accessSources($connection, $data);
        $result = [
            'projects' => Status::UNCHECKED,
            'clips' => Status::UNCHECKED,
            'accounts' => Status::UNCHECKED,
            'publications' => Status::UNCHECKED,
            'account_count' => null,
            'metrics' => Status::UNSUPPORTED,
            'titles' => [],
            'sample' => null,
            'source' => null,
            'project_id' => $data->project_id ?? $targets?->first()?->project_id,
        ];

        try {
            $reader = $this->readers->forConnection($connection);
        } catch (ValidationException) {
            $result['projects'] = $result['accounts'] = Status::UNAVAILABLE;

            return $result;
        }

        $result['projects'] = $this->probe(function () use ($reader, $connection, $targets, &$result, &$sample): void {
            $sources = collect($this->readers->sources($reader, $connection));
            $result['titles'] = array_values($sources->map(static fn($source): string => $source->title ?? 'Проект без названия')->all());
            $sample = $targets instanceof Collection ? $sources->whereInStrict('id', $targets->pluck('external_id'))->first() : $sources->first();
        });

        if (isset($sample)) {
            $result['source'] = $sample->id;
            $result['sample'] = $sample->title ?? 'Проект без названия';
            $result['clips'] = $this->probe(fn(): \Illuminate\Support\Collection => $reader instanceof ClippingResourceReader ? $reader->clipMetadata($sample->id) : $reader->clips($sample->id));

            if ($reader instanceof ClippingResourceReader) {
                $result['publications'] = $this->probe(fn(): \Illuminate\Support\Collection => $reader->clipPublications($sample->id));
            }
        }

        $result['accounts'] = $reader::readsAccounts() ? $this->probe(function () use ($reader, &$result): void {
            $result['account_count'] = count($reader->accounts());
        }) : Status::UNSUPPORTED;

        return $result;
    }

    private function probe(Closure $read): Status
    {
        try {
            $read();

            return Status::AVAILABLE;
        } catch (ClippingProviderException $exception) {
            return in_array($exception->status, [401, 403], true) ? Status::DENIED : Status::UNAVAILABLE;
        } catch (RuntimeException) {
            return Status::UNAVAILABLE;
        }
    }
}
