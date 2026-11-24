<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\GetSeriesExportAction;
use App\Http\Controllers\Controller;
use App\Models\SeriesAssembly;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /**
     * Подписанный экспорт принятого итогового ролика
     *
     * @group Сериалы
     * @unauthenticated
     * @urlParam assembly integer required ID итогового монтажа. Example: 1
     * @urlParam media integer required Собственный файл монтажа. Example: 1
     * @queryParam checksum string SHA-256 выбранного файла выпуска.
     * @response 200
     * @response 403
     * @response 404
     */
    public function __invoke(SeriesAssembly $assembly, Media $media, GetSeriesExportAction $action, Request $request): BinaryFileResponse
    {
        return $this->privateFile($action->run($assembly, $media, checksum: $request->has('checksum') ? $request->string('checksum')->toString() : null));
    }
}
