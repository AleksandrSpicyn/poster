<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SaveAudioAction;
use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\SunoData;

/**
 * HTTP-граница callback Suno: валидированный конверт передаётся в SaveAudioAction.
 * Внешний task ID, а не сессия пользователя, связывает callback с генерацией.
 */
class SunoController
{
    /**
     * Callback генерации Suno
     *
     * Публичный callback без подписи в текущем контракте. Промежуточные статусы, неизвестный task_id и пустой audio_url подтверждаются пустым 200 без сохранения. Сохраняется первый трек.
     *
     * @group Webhooks
     * @unauthenticated
     * @header Accept application/json
     * @response 200
     * @bodyParam code integer required Код провайдера. Example: 200
     * @bodyParam msg string required Сообщение провайдера. Example: success
     * @bodyParam data object required Результат генерации.
     * @bodyParam data.callbackType string required Тип callback; только complete сохраняет аудио. Example: complete
     * @bodyParam data.task_id string required ID задания Suno. Example: task-1
     * @bodyParam data.data object[] required Непустой массив треков.
     * @bodyParam data.data[].id string required ID трека. Example: track-1
     * @bodyParam data.data[].audio_url string URL аудио или null. Example: https://cdn.example.com/audio.mp3
     * @response 422 scenario="Validation error (JSON)" {"message": "The code field is required.", "errors": {"code": ["The code field is required."]}}
     */
    public function __invoke(SunoData $data, SaveAudioAction $audioAction): void
    {
        $audioAction->run($data->data);
    }
}
