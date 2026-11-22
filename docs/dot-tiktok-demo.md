# Задание для Dot — Direct Post

Используй эту инструкцию после выкладки версии с беспарольным кабинетом. Проверь в существующем TikTok-приложении Sandbox, target users, Web Login Kit, Display API, Content Posting API и включение Direct Post. Точный callback — HTTPS-адрес стенда с путём `/demo/tiktok/callback`. Кабинет Direct Post запрашивает `user.info.basic`, `video.list` и `video.publish`. Кабинет открывается по `/demo/tiktok/authors`.

Оставь `TIKTOK_DEMO_MODE=sandbox` и `TIKTOK_DEMO_UPLOAD=false`. Ключи Sandbox передай через приватный .env основного Poster. Пароли демо-авторов больше не нужны. Передай владельцу ввод данных TikTok, MFA и согласие. Проверь профиль и пустое состояние списка либо реальные метрики. Для второго автора используй отдельный браузер. По [сценарию записи](tiktok-demo.md) продемонстрируй выбор собственного файла, предпросмотр, ограничения видимости, маркировки и согласие. Для реальной отправки потребуется отдельное поручение владельца.

Верни отчёт о проверенных шагах и доступности `video.publish`. До одобрения и аудита сохраняй Sandbox. Не отправляй заявку на модерацию, не выдавай тестовую демонстрацию за запущенный публичный сервис. После одобрения инструкция стенда предусматривает один переключатель для использования заранее настроенных Production-ключей.

## Запись и пакет для review

1. На Developer Portal открой существующее приложение. Сверь название и иконку, фактическое описание продукта, адрес сайта, доступные без входа страницы Privacy Policy и Terms of Service и точный Web Redirect URI. Записывай на том же домене, который указан в заявке. Для первой заявки TikTok требует демонстрацию из Sandbox.
2. Подготовь собственный короткий MP4 до 960 КиБ, права на который подтверждает автор. Запиши окно браузера. Начни с открытого кабинета, покажи переход по «Подключить TikTok», экран согласия и возврат с именем владельца. Останови захват на вводе данных и MFA. Скрывай параметры callback, ключи, токены и приватные уведомления. Для Display API покажи реальные метрики у согласившегося автора с публичным роликом. У пустого аккаунта покажи пустой список.
3. Покажи путь Direct Post от выбора файла до предпросмотра, редактирования подписи, выбора аудитории и настроек взаимодействия. Продемонстрируй маркировку коммерческого контента и согласие с условиями музыки. Для сквозной записи согласуй с владельцем **одну приватную публикацию** на подходящем тестовом аккаунте. После его подтверждения выбери «Только я», отправь и обнови статус до подтверждения TikTok. Покажи результат в аккаунте владельца, сохранив приватную видимость. Без согласия заверши репетицию перед отправкой и пометь запись как неполную.
4. Сверь выбранные права на уровне всего приложения. Основной Poster отдельно использует `video.upload` для входящих TikTok. Если это право уже одобрено, сохрани его. Если оно входит в рассматриваемую заявку, покажи реальный сценарий входящих отдельным фрагментом с согласия автора. В кабинете Direct Post черновики выключены.
5. Экспортируй читаемую запись (до пяти видео, до 50 МБ каждое). Для каждого права укажи краткое обоснование и таймкод: профиль — `user.info.basic`, публичные ролики и метрики — `video.list`, публикация выбранного автором видео — `video.publish`, отправка отдельного черновика во входящие — `video.upload`. Для повторного review опиши также используемые ранее продукты и права.
6. В App Review подготовь заявку с URL кабинета, политиками, описанием сценария и записями. Для снятия ограничения приватной видимости нужен также аудит Direct Post через ссылку на audit в руководстве Content Posting API. Укажи реальную аудиторию, кто создаёт контент и кто принимает решение о публикации. Если сервис остаётся инструментом команды, сообщи владельцу о несоответствии критерию TikTok. Перед Submit передай владельцу черновик, видео и список непроверенных шагов. После его разрешения отправь и сохрани статус; на запрос TikTok исправь конкретное замечание и обнови соответствующий фрагмент записи. Решение принимает TikTok.
7. До одобрения сохрани режим sandbox. Когда одобрены приложение, нужные scopes и снято ограничение Direct Post, проверь Production-ключи и callback, переключи режим по инструкции стенда и попроси автора пройти OAuth заново.

[Требования к App Review и видео](https://developers.tiktok.com/doc/app-review-guidelines/) · [Правила Direct Post](https://developers.tiktok.com/doc/content-sharing-guidelines/) · [Разрешение и аудит](https://developers.tiktok.com/doc/content-posting-api-get-started/)

## Текст для полей заявки

**Product description.** Poster is a short video workspace. The creator connects their TikTok account, reviews their public videos, and chooses an original video from their device to share. Before sending it, they preview the file, edit its caption, choose an audience and confirm the post. Poster then displays the processing result returned by TikTok.

| Permission | Explanation for review |
| --- | --- |
| user.info.basic | Show the connected account’s display name so the creator can verify the destination before posting. |
| video.list | Display the creator’s public videos with available view, like, comment and share counts. |
| video.publish | Send the creator-selected video to their account with the caption, audience, disclosures and interaction settings they confirmed. |

Intended audience: Independent creators publishing their own original videos. Creators open the workspace from the public Poster homepage and authorize their own TikTok account. During Sandbox review, TikTok access is restricted to enabled testers. После записи проставь фактические таймкоды для каждого разрешения. Сохрани итоговые видео и список выбранных прав перед отправкой владельцу.
