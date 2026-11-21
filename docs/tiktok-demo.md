# Демо кабинета автора Poster

Это временная демонстрация будущего продукта. Два тестовых автора входят с разными паролями и каждый сам разрешает доступ к своему TikTok. Подключение живёт в серверной сессии этого браузера. После окончания сессии или срока действия токена нужно подключиться снова. Демо не создаёт пользователей Poster, каналов, проектов, заданий или публикаций.

## Запуск

1. В **Sandbox существующего приложения** TikTok добавьте владельцев как target users, включите Login Kit, Display API и Content Posting API с возможностью загрузки во входящие. Для обоих нужны ровно `user.info.basic`, `video.list`, `video.upload`. Зарегистрируйте в Web Login Kit точный HTTPS URI вашего стенда с путём `/demo/tiktok/callback`. Используйте ключ и секрет именно этой Sandbox-конфигурации. [Sandbox](https://developers.tiktok.com/doc/add-a-sandbox), [Display API](https://developers.tiktok.com/doc/tiktok-api-v2-video-list), [загрузка во входящие](https://developers.tiktok.com/doc/content-posting-api-reference-upload-video).
2. В отдельной копии Poster с установленными зависимостями создайте приватный `.env.demo` на основе `.env.example`. Укажите свой HTTPS `APP_URL`, создайте отдельный ключ командой `php artisan key:generate --env=demo`. Настройте независимые каталоги хранения и кэша. Для входа используется браузерный диалог HTTP Basic. Подготовьте для каждого автора отдельный хеш. Например, в bash выполните команду ниже дважды, сохранив результаты в соответствующие переменные. В dotenv заключайте хеши в одинарные кавычки.

   ```bash
   read -rs -p 'Пароль демо-автора: ' demo_password
   printf '%s' "$demo_password" | php -r 'echo password_hash(stream_get_contents(STDIN), PASSWORD_DEFAULT), PHP_EOL;'
   unset demo_password
   ```

   ```dotenv
   APP_ENV=demo
   APP_DEBUG=false
   CACHE_STORE=file
   SESSION_DRIVER=file
   SESSION_COOKIE=poster_tiktok_demo
   SESSION_SECURE_COOKIE=true
   SESSION_SAME_SITE=lax
   TIKTOK_DEMO_ENABLED=true
   TIKTOK_DEMO_AUTHOR_A_HASH=''
   TIKTOK_DEMO_AUTHOR_B_HASH=''
   TIKTOK_DEMO_SANDBOX_KEY=
   TIKTOK_DEMO_SANDBOX_SECRET=
   TIKTOK_DEMO_REDIRECT_URI=https://demo.example/demo/tiktok/callback
   TIKTOK_DEMO_UPLOAD=false
   ```

3. Запустите `php artisan serve --env=demo --host=127.0.0.1 --port=8017` за HTTPS-прокси стенда. Проксируйте путь `/demo/tiktok/`, передавайте исходный Host и заголовок Authorization. В журнале прокси сохраняйте только путь без query string, паролей и заголовков. Откройте `/demo/tiktok/authors`, войдите как `author-a` или `author-b`. Для второго автора используйте другой профиль браузера. В окружении production маршруты вернут 404.
4. Для действия с черновиком нужен подготовленный файл `storage/app/private/tiktok-demo/sample.mp4` до 10 МиБ. Для создания нейтрального четырёхсекундного образца используйте команды ниже. Только после отдельного согласования включите `TIKTOK_DEMO_UPLOAD=true` и перезапустите процесс с новой конфигурацией. Одна попытка на подключение. При неопределённом результате сначала проверьте входящие в приложении.

   ```bash
   mkdir -p storage/app/private/tiktok-demo
   ffmpeg -n -f lavfi -i testsrc2=size=360x640:rate=24 -t 4 -c:v libx264 -pix_fmt yuv420p -movflags +faststart storage/app/private/tiktok-demo/sample.mp4
   ```

## Сценарий записи для модерации

1. Представьте Poster как демонстрацию будущего кабинета автора. Начните запись с открытого кабинета после ввода тестового пароля.
2. Нажмите «Подключить TikTok». Покажите выбор аккаунта, экран согласия и три разрешения. После возврата покажите имя, полученное от TikTok. Скрывайте адресную строку на шаге обратного перехода с кодом.
3. Покажите результат запроса публичных роликов. Для аккаунта без публикаций оставьте в кадре сообщение о пустом списке. Если у другого согласившегося автора есть публичные видео, покажите его отдельный кабинет и счётчики. Прочерк означает отсутствие значения у провайдера.
4. Покажите кнопку «Отправить черновик в TikTok» и объясните, что автор завершает публикацию в приложении. При выключенной отправке закончите запись здесь. Для записи фактической передачи требуется отдельное согласование, после которого можно показать отправку и входящее уведомление. До нажатия кнопки подтверждения в самом TikTok остановите запись. Игровой процесс через OpusClip работает отдельно.

## Проверка и удаление

`php artisan test --compact tests/Feature/TiktokDemo` проверяет маршруты с изолированной сессией и HTTP-fakes. Для живой проверки Login Kit и Display API нужны Sandbox-ключи, HTTPS callback и согласие владельца в браузере. Отправку файла можно проверять независимо от чтения статистики.

Для выключения установите `TIKTOK_DEMO_ENABLED=false` и перезагрузите конфигурацию стенда. После записи отзовите разрешения тестовых пользователей в TikTok, удалите демо-callback и тестовых пользователей из Sandbox. Удалите отдельную копию стенда с её `.env.demo`, серверными сессиями и тестовым файлом. В коде удалите подключение `tiktok-demo.php` из `routes/web.php`, сам файл маршрутов, `config/tiktok_demo.php`, каталоги `TiktokDemo` в Actions, Data, Tasks и Controllers, middleware `AuthenticateTiktokDemo`, шаблон `resources/views/tiktok-demo`, тесты `tests/Feature/TiktokDemo`, helper `tests/Support/TiktokDemo.php` с его подключением в `tests/Pest.php` и эту инструкцию. Миграций для демо нет.
