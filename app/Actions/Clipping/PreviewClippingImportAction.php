<?php

declare(strict_types=1);

namespace App\Actions\Clipping;

use App\Classes\Clipping\ClippingReaders;
use App\Data\ClippingImportData;
use App\Models\ClippingConnection;
use App\Models\ClippingImport;
use App\Repositories\ClippingAccountRepository;
use App\Repositories\ClippingConnectionRepository;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final readonly class PreviewClippingImportAction
{
    public function __construct(private ClippingReaders $readers, private ClippingConnectionRepository $connections, private ClippingAccountRepository $accounts) {}

    public function run(ClippingConnection $connection, ClippingImportData $data): ClippingImport
    {
        if ($data->project_id !== null) {
            abort_unless($this->connections->projects($connection)->contains('id', $data->project_id), 404);
        }

        $reader = $this->readers->forConnection($connection);

        try {
            $available = $data->accounts !== [] && $reader::readsAccounts() ? $reader->accounts() : [];

            foreach ($reader->sources() as $source) {
                if ($source->id === $data->external_id) {
                    $clips = $reader->clips($source->id);

                    break;
                }
            }
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['external_id' => 'Провайдер не вернул данные для предпросмотра. Повторите попытку.']);
        }

        if (! isset($clips, $source)) {
            throw ValidationException::withMessages(['external_id' => 'Источник недоступен этому подключению.']);
        }

        return $this->connections->preview($connection, $source, $data, $clips, $this->accounts->preview($connection, $data->accounts, $available));
    }
}
