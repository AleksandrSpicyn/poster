# Dot — подача Poster на TikTok App Review и аудит Direct Post

Владелец поручает тебе собрать материалы и подать заявку для **существующего приложения Poster**. Кабинет остаётся постоянной функцией для внешних авторов, которые подключают собственные аккаунты и выбирают свои видео. Рабочий сайт развёрнут. До одобрения интеграция кабинета использует Sandbox. Доведи работу до отправки и сохрани подтверждение портала. Если заявка уже на рассмотрении, зафиксируй её текущий статус и номер.

## Открой приложение и сверь адреса

Войди в [TikTok Developer Portal](https://developers.tiktok.com/apps/) под учётной записью владельца приложения. На ввод пароля, MFA и обязательное личное подтверждение передай управление владельцу. Проверь название Poster, иконку, платформу Web, Login Kit, Display API и Content Posting API с Direct Post.

| Поле | Значение |
| --- | --- |
| Website URL | `https://24i-poster.ru/` |
| Reviewer entry point | `https://24i-poster.ru/demo/tiktok/authors` |
| Privacy Policy | `https://24i-poster.ru/policy` |
| Terms of Service | `https://24i-poster.ru/terms` |
| Web redirect URI | `https://24i-poster.ru/demo/tiktok/callback` |

Открой главную без входа и пройди по ссылке в кабинет. Для записи используй Sandbox и тот же домен. Сохрани текущие ключи, действующие подключения и режим сервера.

## Подготовь доказательства

- Вступление уже записано на рабочем сайте. Файл лежит в проекте по пути `storage/app/private/tiktok-demo/creator-workspace-overview.mp4`. Он показывает главную и вход в кабинет. Перед загрузкой проверь воспроизведение.
- Запиши Login Kit с разрешённым Sandbox-пользователем. Покажи экран разрешений, возврат в Poster и отображаемое имя получателя. Для Display API покажи список собственных публичных роликов с доступными счётчиками. Можно снять отдельный фрагмент с другим согласившимся тестером, у которого есть публичный ролик.
- Для Direct Post используй приватный тестовый аккаунт. На AI Fails ранее получен реальный `PUBLISH_COMPLETE`. Если есть полная запись этого запуска, используй её. Иначе согласуй с владельцем **одну новую приватную публикацию** и конкретный оригинальный MP4 до 960 КиБ. Покажи выбор файла, предпросмотр, редактирование подписи, ручной выбор «Только я», настройки взаимодействий, маркировку и согласие. После отправки обнови статус и покажи подтверждение в Poster и ролик в приватной вкладке TikTok. При неопределённом результате проверь статус до любых повторных действий.
- Исключи из записи пароли, MFA, коды OAuth, токены и личные уведомления. Просмотри экспорт перед загрузкой. TikTok принимает до пяти видео, до 50 МБ каждое. Запиши фактические таймкоды подключения, отображения роликов, отправки и подтверждения. [Правила App Review](https://developers.tiktok.com/docs/en/app-review-guidelines)

## Заполни и отправь

1. В кабинете автора используются `user.info.basic`, `video.list` и `video.publish`. Сверь их с реально выбранными продуктами и правами в заявке. У основного Poster отдельно существует отправка во входящие с `video.upload`. Сохрани ранее выданные разрешения. Если портал рассматривает и `video.upload`, приложи обоснование и отдельную демонстрацию этой функции с согласия автора.
2. Заполни App Review текстом ниже, приложи записи и поставь рядом с каждым разрешением его таймкод. Для повторного review объясни также уже подключённые продукты и права. Указывай фактические сведения об аудитории, доступе и использовании. Если портал требует число пользователей или юридические сведения, которых у тебя нет, запроси конкретное значение у владельца.
3. Проверь итоговый экран, нажми **Submit for review** и сохрани показанный статус, дату, номер или ссылку заявки. Поручение на эту отправку уже дано. Если портал требует личной подписи владельца, передай ему только этот шаг и затем проверь результат.
4. Для снятия ограничения приватной видимости открой [форму аудита Direct Post](https://developers.tiktok.com/application/content-posting-api) из аккаунта владельца. Проверь наличие ранее поданного аудита. Если форма доступна, заполни её для того же приложения, приложи запрошенные материалы и отправь. Если TikTok требует сначала одобрения App Review, зафиксируй это условие и оставшийся шаг. [Правила Direct Post](https://developers.tiktok.com/docs/en/content-sharing-guidelines)
5. Верни владельцу отдельный результат по App Review и Direct Post audit: статус как в портале, номер или ссылка (если выданы), какие видео и права приложены, следующий ожидаемый ответ. Если действие заблокировано, укажи точный текст сообщения и экран. Режим Sandbox сохраняется до отдельного решения о переключении после одобрения.

## Текст для полей заявки

**Product description.** Poster is a short video workspace. The creator connects their TikTok account, reviews their public videos, and chooses an original video from their device to share. Before sending it, they preview the file, edit its caption, choose an audience and confirm the post. Poster then displays the processing result returned by TikTok.

| Permission | Explanation for review |
| --- | --- |
| user.info.basic | Show the connected account’s display name so the creator can verify the destination before posting. |
| video.list | Display the creator’s public videos with available view, like, comment and share counts. |
| video.publish | Send the creator-selected video to their account with the caption, audience, disclosures and interaction settings they confirmed. |

Intended audience: Independent creators publishing their own original videos. Creators open the workspace from the public Poster homepage and authorize their own TikTok account. During Sandbox review, TikTok access is restricted to enabled testers.
