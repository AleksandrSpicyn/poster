# Интеграция пакетов

## До изменения

Найдите установленную версию по lockfile/package metadata и используйте её
официальную документацию. Диапазон в manifest не доказывает точную версию.
Проверьте существующие wrappers, опубликованную конфигурацию, custom models,
миграции, providers и настройки discovery. Не устанавливайте альтернативный
пакет и не выполняйте publish/config changes вне разрешённого scope.

## Карточка интеграции

Для значимой зависимости зафиксируйте в документации целевого приложения:

- пакет, установленную версию/совместимость и назначение
- официальную документацию и upgrade guide
- используемые API и границу ответственности приложения
- необходимые конфигурации, migrations, providers и discovery paths
- хранение секретов, данных и требования доступа
- failure/retry/cache behavior, тесты и существенные ограничения

Эта карточка содержит факты конкретного приложения; общие правила в данном
репозитории не должны превращаться в копию его environment config.

## Два профиля размещения

В стандартном профиле адаптеры могут находиться в `app/Services` или принятом
каталоге integrations, модели — в `app/Models`, migrations — в `database`.
В модульном профиле интеграцией владеет доменный модуль, например
`app/Modules/{Module}/Integrations`, а связанные модели и migrations находятся
у того же владельца. Общие адаптеры выделяются только при настоящем переиспользовании.

Стандартный namespace пакета может не обнаружить нестандартный путь автоматически.
Проверьте регистрацию migrations, factories, DTO/settings discovery, views,
translations и cache generation. Не обходите механизм расширения пакета прямой
правкой vendor или дублирующим provider.

## Границы и побочные эффекты

Wrapper полезен, если изолирует внешний контракт, доменную политику или сменяемую
реализацию. Один локальный вызов не требует интерфейса по умолчанию.
Сохраняйте пакетные методы, управляющие validation, events, cache и cleanup;
прямая запись таблиц может обойти эти эффекты.

Для DTO уточняйте validation/resolution и output serialization. Для media —
collection, disk, visibility, conversions и очистку. Для settings — типы,
default migrations, discovery и cache lifecycle. Для permissions — actor guard,
модели, scoped access и механизм cache invalidation. Конкретные команды и методы
подбираются по установленной версии, а не берутся из универсального шаблона.

## Проверка и обновления

Используйте доступные fakes/isolated modes, проверяя собственный контракт
интеграции и важные отказы. Не повторяйте внутреннюю test suite пакета.
Отдельно проверьте runtime, если результат зависит от worker reload или cache.

При обновлении изучите breaking changes, опубликованную config, schema/data
migration, serialized jobs и обратную совместимость. План должен учитывать
сосуществование старого и нового кода во время доставки.

Связанные правила: [architecture.md](architecture.md), [testing.md](testing.md),
[authorization.md](authorization.md).

## Примеры Spatie: версия и namespace указаны явно

Эти примеры опираются на публичные API указанных major versions, а не на
application wrappers. В целевом приложении проверьте установленную версию,
конфигурацию и совместимость PHP/Laravel до копирования.

### Laravel Data v4: DTO с validation attributes

```php
namespace App\Dto;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class StoreArticleData extends Data
{
    public function __construct(
        #[Required, StringType, Max(200)]
        public string $title,
        #[Required, BooleanType]
        public bool $is_published,
    ) {}
}
```

Это альтернатива обычному DTO/Form Request из архитектурного примера, а не его
вторая декларация. Для module profile меняется namespace на
`App\Modules\Publishing\Dto`. Контроллер использует request resolution/validation
по установленной конфигурации пакета либо явный `validateAndCreate`.
Не считайте `Data::from($array)` безусловной гарантией validation во всех режимах.
Поля actor/owner передаются отдельно, не принимаются доверенно из DTO.
См. [Laravel Data v4 validation attributes](https://spatie.be/docs/laravel-data/v4/validation/using-validation-attributes).

### Media Library v11: отдельный приватный документ

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class Document extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')->useDisk('private');
    }
}
```

Disk `private` должен быть настроен приложением с закрытым доступом. Имя disk
само по себе не гарантирует privacy. Проверяйте download authorization и срок
жизни временной ссылки. Файл валидируется до конверсии, media mutation сохраняет
пакетные hooks и cleanup. См. [Media Library v11 collections](https://spatie.be/docs/laravel-medialibrary/v11/working-with-media-collections/defining-media-collections).

### Permission: публичные методы вместо raw pivot sync

При установленном совместимом `spatie/laravel-permission`, модели с `HasRoles`
и заранее созданных identifiers:

```php-fragment
$actor->assignRole('editor');
$actor->givePermissionTo('articles.publish');
$canPublish = $actor->can('articles.publish');
```

Имена `editor` и `articles.publish` — пример словаря, не обязательный enum
или fixed role. Guard permissions должен соответствовать actor guard.
Прямой `roles()->sync(...)` ради назначения может обойти пакетные правила/cache;
сохраняйте API пакета. Cache invalidation проверьте отдельно для custom writes.
Пример этих методов: [Permission v6 role/permission usage](https://spatie.be/docs/laravel-permission/v6/basic-usage/role-permissions).

### Settings: типизированная граница

Форма settings-класса для пакета `spatie/laravel-settings`, поддерживающего
`Settings::group()`; точную совместимость и migration API сверяйте с его версией:

```php
namespace App\Settings;

use Spatie\LaravelSettings\Settings;

final class PublishingSettings extends Settings
{
    public int $maxTitleLength;

    public static function group(): string
    {
        return 'publishing';
    }
}
```

`maxTitleLength` требует сохранённого default через settings migration либо
другой поддерживаемый механизм; PHP type не создаёт значение в storage.
Для модуля проверьте discovery path. Не считайте cache generation доказательством
наличия defaults и корректного поведения уже работающего worker.

Справочник формы settings-класса: [официальный README Laravel Settings](https://github.com/spatie/laravel-settings/blob/main/README.md).
