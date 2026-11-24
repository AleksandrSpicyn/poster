<?php

declare(strict_types=1);

namespace App\Http\Controllers\EpisodeScripts;

use App\Actions\Episodes\SaveEpisodeScriptAction;
use App\Data\Episodes\SaveEpisodeScriptData;
use App\Http\Controllers\EpisodeController;
use App\Models\Episode;
use App\Models\Series;
use Illuminate\Http\RedirectResponse;

class StoreController extends EpisodeController
{
    /**
     * Сохранение новой версии сценария
     *
     * Требуются сессия, подтверждённый email и CSRF для изменений.
     * Вложенные версии, сцены, планы и сборки ограничены родителями.
     *
     * @group Кабинет
     * @subgroup Эпизоды
     * @authenticated
     * @urlParam series integer required ID сериала. Example: 1
     * @urlParam episode integer required ID эпизода. Example: 1
     * @bodyParam script object required Структурированный сценарий со сценами и планами.
     * @bodyParam script.title string required Название до 255 символов. Example: The locked door
     * @bodyParam script.objective string required Цель эпизода до 5000 символов. Example: Find the key.
     * @bodyParam script.character_revision_ids integer[] required ID утверждённых версий персонажей этого сериала, 1–10 разных значений. Example: [1]
     * @bodyParam script.scenes object[] required От 1 до 20 сцен, всего до 100 планов и 300000 мс.
     * @bodyParam script.scenes[].title string required Название сцены до 255 символов. Example: The hallway
     * @bodyParam script.scenes[].location string required Место до 1000 символов. Example: Café hallway
     * @bodyParam script.scenes[].time string required Время до 255 символов. Example: Evening
     * @bodyParam script.scenes[].objective string required Цель сцены до 2000 символов. Example: Open the door.
     * @bodyParam script.scenes[].initial_state string Начальное состояние до 4000 символов. Example: The door is closed.
     * @bodyParam script.scenes[].expected_final_state string Ожидаемый финал до 4000 символов. Example: The key is found.
     * @bodyParam script.scenes[].is_closed boolean required Планируется ли завершить сцену. Example: false
     * @bodyParam script.scenes[].shots object[] required От 1 до 30 планов сцены в порядке монтажа.
     * @bodyParam script.scenes[].shots[].duration_ms integer required Длительность 4000–30000 мс; для видеомоделей нужна целая секунда. Example: 5000
     * @bodyParam script.scenes[].shots[].action string required Действие до 5000 символов. Example: The hero picks up the key.
     * @bodyParam script.scenes[].shots[].camera string Камера до 2000 символов. Example: Medium shot
     * @bodyParam script.scenes[].shots[].transition_mode string required Начало плана. Enum: continue_scene,new_shot,new_scene Example: new_scene
     * @bodyParam script.scenes[].shots[].dialogue object[] До 20 реплик; при silent массив пустой. Example: []
     * @bodyParam script.scenes[].shots[].dialogue[].character_id integer required Постоянный ID говорящего из выбранных версий персонажей. Example: 1
     * @bodyParam script.scenes[].shots[].dialogue[].text string required Реплика до 2000 символов. Example: I found the key.
     * @bodyParam script.scenes[].shots[].source_prompt_id integer Готовый дубль предыдущего плана этого эпизода или null. Example: null
     * @bodyParam script.scenes[].shots[].source_assembly_id integer Принятая сборка источника или null; несовместимо с source_prompt_id. Example: null
     * @bodyParam script.scenes[].shots[].source_time_ms integer Точка продолжения в миллисекундах от 0; 0 выбирает финал источника. Example: 0
     * @bodyParam lock_version integer required Версия эпизода. Example: 0
     * @response 302
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"Your email address is not verified."}
     * @response 404
     * @response 422
     */
    public function __invoke(Series $series, Episode $episode, SaveEpisodeScriptData $data, SaveEpisodeScriptAction $action): RedirectResponse
    {
        $action->run($episode, $data);

        return $this->redirectToEpisode($series, $episode);
    }
}
