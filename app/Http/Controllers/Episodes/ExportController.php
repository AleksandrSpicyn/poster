<?php

declare(strict_types=1);

namespace App\Http\Controllers\Episodes;

use App\Actions\Episodes\GetEpisodeExportAction;
use App\Http\Controllers\Controller;
use App\Models\EpisodeAssembly;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportController extends Controller
{
    /**
     * Ограниченный экспорт принятого видео для провайдера публикации
     *
     * Доступ по действующей временной подписи без сессии. Только текущая принятая
     * сборка, её собственное episode-video и актуальная история эпизода.
     *
     * @group Сериалы
     * @unauthenticated
     * @urlParam assembly integer required ID принятой сборки. Example: 1
     * @urlParam media integer required ID видео сборки. Example: 1
     * @queryParam expires integer required Время истечения подписанной ссылки. Example: 1790000000
     * @queryParam signature string required Подпись Laravel. Example: signature
     * @response 200
     * @response 403
     * @response 404
     */
    public function __invoke(EpisodeAssembly $assembly, Media $media, GetEpisodeExportAction $action, Request $request): BinaryFileResponse
    {
        return $this->privateFile($action->run($assembly, $media, $request->has('checksum') ? $request->string('checksum')->toString() : null));
    }
}
