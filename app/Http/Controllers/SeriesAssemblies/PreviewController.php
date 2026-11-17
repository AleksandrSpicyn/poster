<?php

declare(strict_types=1);

namespace App\Http\Controllers\SeriesAssemblies;

use App\Actions\Series\GetSeriesExportAction;
use App\Models\SeriesAssembly;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PreviewController
{
    /**
     * Приватный просмотр итогового монтажа
     *
     * @group Сериалы
     * @authenticated
     * @urlParam assembly integer required ID итогового монтажа. Example: 1
     * @urlParam media integer required Собственный файл монтажа. Example: 1
     * @response 200
     * @response 403
     * @response 404
     */
    public function __invoke(SeriesAssembly $assembly, Media $media, GetSeriesExportAction $action): BinaryFileResponse
    {
        return response()->file($action->run($assembly, $media, true), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->setPrivate();
    }
}
