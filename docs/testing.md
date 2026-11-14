## Соглашения Poster

- Feature HTTP: отдельный файл на HTTP method + named route, рядом с путём
  контроллера в `tests/Feature/Controllers`; closure/redirect routes — в `tests/Feature/Routes`
- Unit: один проверяемый класс на файл, путь повторяет `app/`; интеграционные
  сценарии нескольких классов располагаются в Feature, а не Unit
- Pest helpers импортируются через `use function`, `$this` не используется
- Между imports классов и функций — пустая строка; внутри каждой группы её нет
- В запросах и проверках переходов используются имена маршрутов через `route()`
- Подготовка/fakes, вызов сценария и assertions отделяются пустыми строками;
  методы в цепочках переносятся на отдельные строки
- В `tests/Pest.php` остаются общие hooks и подключение helper-файлов.
  Helper в `tests/Support` должен использоваться в нескольких файлах;
  локальные builders размещаются в файле соответствующего сценария

# Тестирование Laravel-приложения

## 1. Назначение и профили

Тест защищает наблюдаемое поведение, контракт или важный отказ. Требования
одинаковы для двух профилей из [architecture.md](architecture.md); меняются пути,
namespace и настройка обнаружения.

| Назначение | Стандартный Laravel | Модульный Laravel |
|---|---|---|
| HTTP/DB/integration | `tests/Feature` | `app/Modules/{Module}/Tests/Feature` |
| Изолированная логика | `tests/Unit` | `app/Modules/{Module}/Tests/Unit` |
| Admin feature | `tests/Feature/Admin` | `app/Admin/{Module}/Tests/Feature`, если admin выделен |
| Фабрики | `database/factories` | `app/Modules/{Module}/Database/Factories` |
| Локальные fixtures | `tests/Fixtures` или рядом с владельцем | `{Module}/Tests/Fixtures` |

В сокращённых путях `{Module}` означает корень доменного модуля.
Пути — примеры выбранных профилей. Не перемещайте существующие тесты ради
таблицы и не создавайте общий helper без реального повторного использования.

## 2. Настройка запуска

До добавления теста проверьте установленные версии Pest/PHPUnit, test suites,
autoload-dev, bootstrap, базовый TestCase, DB traits и переменные тестовой среды.
Для модулей явно проверьте, что runner находит их тесты и миграции, а Pest
применяет Laravel TestCase к модульным Feature-файлам. Само имя `Tests/Feature`
не включает загрузку приложения.

Группы, язык описаний, `it`/`test`, `$this`, импорт функций и файловая организация
следуют локальным правилам. Не выдавайте соглашения конкретной команды за
ограничения Pest. Имена сценариев объясняют ожидаемое поведение.

## 3. Граница теста

- Unit-тест проверяет изолированную логику без загрузки Laravel, HTTP и БД.
- Feature/integration-тест проверяет приложение, persistence или взаимодействие
  компонентов. Наличие слова Unit в пути не делает DB-тест изолированным.
- HTTP-контракт проверяется через маршрут с настоящими middleware и validator.
  Прямой вызов контроллера не заменяет такую проверку.
- Не подменяйте Action/Task/Repository в тесте, который должен проверить их
  поведение. Для отдельного orchestration/unit-контракта изоляция допустима.

## 4. Данные и изоляция

Используйте factories/states и отношения `for`, `has`, `hasAttached`, когда они
выражают нужный сценарий. Прямые relation/query API уместны, если тестируется
именно этот API или фабрика делает подготовку менее понятной.

Создавайте минимально достаточные данные, включая записи-ловушки для фильтров
и чужого scope. Не полагайтесь на случайное совпадение значений Faker.
Переиспользуйте понятные states вместо повторяющейся сложной подготовки.

Тесты используют отдельную БД и тестовые интеграции. Восстанавливайте изменённый
cache/config/static state, время и глобальные registries. Factory setup может
создавать события и activity records: устанавливайте baseline после подготовки.
Не запускайте конкурирующие suites на одной изменяемой БД; параллельность требует
раздельных ресурсов и настройки runner.

## 5. Матрица HTTP-сценариев

Выбирайте применимые случаи, а не создавайте одинаковый checklist для каждого endpoint.

| Сценарий | Содержательная проверка |
|---|---|
| Успех чтения | Статус, поля/типы ответа, вложенность и ожидаемые пропуски |
| Успех записи | Ответ, изменённое состояние, необходимые побочные эффекты |
| Нет аутентификации | Фактический ответ guard/handler, отсутствие записи |
| Нет права / чужой scope | Запрет или скрывающий `404`, неизменность данных |
| Ошибка ввода | Статус, точные поля ошибок, отсутствие эффектов |
| Нет модели | Реальный binding/domain-сценарий |
| Фильтр/поиск | Наличие совпадений и отсутствие неподходящих записей |
| Сортировка/пагинация | Порядок, границы страницы, реальный envelope |
| Partial update | Изменение указанных полей, сохранение остальных |
| Файл/импорт | Хранилище, persistence, ограничения и очистка при отказе |

Обычные проверки прав выполняются актором с необходимым минимальным доступом.
Super-admin не должен маскировать ошибку permission-проверки. Не придумывайте
base admin permission и отдельную admin-модель, если приложение их не имеет.

## 6. Assertions и fakes

Используйте семантические HTTP assertions (`assertOk`, `assertCreated`,
`assertForbidden`, `assertUnprocessable`) и проверки JSON/DB/файлов по смыслу.
При именованных маршрутах используйте `route(...)`, чтобы не дублировать URI.

Статус или число записей достаточны только тогда, когда это весь проверяемый
контракт. Для изменения ответа проверяйте изменённые значения и недопустимые
поля; `assertJsonStructure` само по себе не проверяет значения и отсутствие
лишних полей. Для unit-логики используйте подходящие ожидания runner.

Подменяйте внешние HTTP/SDK, mail, notifications, storage и queue, когда их
реальное выполнение не является предметом теста. Проверяйте важные payload,
адресата, dispatch или отсутствие эффекта. Не пишите assertions на случайные
внутренние вызовы и не подменяйте событие, чей listener должен отработать.
Fake очереди не доказывает выполнение job; проверяйте её поведение отдельно.

Текст локализации проверяйте точно, когда он является предметом контракта.
Обычный validation endpoint обычно проверяет поля ошибок; детали — в
[validation.md](validation.md).

## 7. Покрытие и запуск

Для исправления бага добавьте сценарий, воспроизводящий регрессию. Для новой
логики покройте существенные границы и отказы; не создавайте тесты только ради
процента coverage. Данные dataset должны представлять разные правила/границы.

Начинайте с изменённого файла/фильтра, затем расширяйте запуск при общих рисках
или требованиях проекта. Используйте существующие команды и изолированную среду.
Убедитесь, что выполнены сами тесты, а не получен успешный запуск с нулём cases.
Укажите выполненные проверки и ограничения. Зелёный тест не доказывает deploy
или поведение настоящего worker/runtime.

Справочник инфраструктуры: [Laravel Testing](https://laravel.com/docs/13.x/testing).
Пути модулей и правила сценариев выше — выбираемые соглашения приложения.

## 8. Рабочие примеры для контракта Article

Примеры продолжают [сквозной сценарий](architecture.md): Article принадлежит
User через `user_id`, routes называются `articles.store` и `articles.index`.
Названия ресурсов учебные; содержание тестов не зависит от конкретного продукта.
Каждый блок показывает необходимые imports; при объединении cases в одном файле
не дублируйте `use`. Импорты соответствуют стандартному профилю. Для модулей замените Article
на `App\Modules\Publishing\Models\Article`, пути — по таблице профилей.

### 8.1. Миграция и фабрика

Миграция `database/migrations/..._create_articles_table.php`:

```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
```

`database/factories/ArticleFactory.php`:

```php
namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Article> */
final class ArticleFactory extends Factory
{
    protected $model = Article::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'is_published' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['is_published' => true]);
    }
}
```

В модульном профиле перенесите factory в
`App\Modules\Publishing\Database\Factories`, обновите imports и `$model`.
Добавьте метод в модульную Article, чтобы не полагаться на стандартный resolver:

```php-fragment
protected static function newFactory(): \App\Modules\Publishing\Database\Factories\ArticleFactory
{
    return \App\Modules\Publishing\Database\Factories\ArticleFactory::new();
}
```

Миграция находится в `Publishing/Database/Migrations` и загружается provider.
Factory states выражают понятные условия. Вместо ручного `attach` или `user_id`
в каждом тесте используйте `Article::factory()->for($actor, 'author')`.
Модель Article не требует `fillable` только ради factory setup; HTTP-ввод
в сквозном примере сохраняется явно, без `Model::create($request->all())`.

### 8.2. Bootstrap Pest для двух профилей

В Laravel-приложении с Pest `tests/Pest.php` может содержать:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

Для модульного варианта добавьте отдельный include:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('../app/Modules/*/Tests/Feature', '../app/Admin/*/Tests/Feature');
```

Примеры показывают отдельные варианты конфигурации; в смешанном проекте пути
можно объединить без повторного назначения traits. Relative paths разрешаются
от фактического Pest-файла; проверьте их для установленной версии.
В PHPUnit configuration включите реальные module test directories. Одного
`pest()->in(...)` недостаточно, если runner вообще не discovers эти файлы.
Unit-каталоги не получают Laravel TestCase/RefreshDatabase автоматически.

### 8.3. Создание: ответ и сохранённое состояние

`tests/Feature/ArticleStoreTest.php`; необходимые function imports показаны явно:

```php
use App\Models\Article;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

it('создаёт публикацию текущего пользователя', function (): void {
    $actor = User::factory()->create();

    $response = actingAs($actor)->postJson(route('articles.store'), [
        'title' => 'Переносимый пример',
        'is_published' => false,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Переносимый пример')
        ->assertJsonPath('data.is_published', false)
        ->assertJsonPath('data.author.id', $actor->id)
        ->assertJsonMissingPath('data.author.email');

    assertDatabaseHas(Article::class, [
        'id' => $response->json('data.id'),
        'user_id' => $actor->id,
        'title' => 'Переносимый пример',
        'is_published' => false,
    ]);
});
```

### 8.4. Валидация и запрет назначения чужого владельца

Дополнительные cases того же файла:

```php
use App\Models\Article;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;

it('не создаёт публикацию без обязательного заголовка', function (): void {
    $actor = User::factory()->create();

    actingAs($actor)->postJson(route('articles.store'), [
        'is_published' => false,
    ])->assertUnprocessable()->assertJsonValidationErrors('title');

    assertDatabaseCount(Article::class, 0);
});

it('не позволяет назначить владельца через входные данные', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();

    actingAs($actor)->postJson(route('articles.store'), [
        'title' => 'Попытка сменить владельца',
        'is_published' => true,
        'user_id' => $other->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('user_id');

    assertDatabaseCount(Article::class, 0);
});
```

### 8.5. Список: matching и decoy записи

`tests/Feature/ArticleIndexTest.php`:

```php
use App\Models\Article;
use App\Models\User;

use function Pest\Laravel\actingAs;

it('возвращает только публикации текущего пользователя', function (): void {
    $actor = User::factory()->create();
    $other = User::factory()->create();
    $own = Article::factory()->for($actor, 'author')->create();
    $foreign = Article::factory()->for($other, 'author')->published()->create();

    $response = actingAs($actor)->getJson(route('articles.index'));

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id)
        ->assertJsonPath('data.0.author.id', $actor->id)
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('links');

    expect($response->json('data.*.id'))->toBe([$own->id])->not->toContain($foreign->id);
});
```

Проверки отсутствия `meta`/`links` здесь относятся к явно непагинированному
контракту примера. Для paginator эти поля, наоборот, проверяются как часть ответа.
Файл содержит один endpoint; это рекомендуемый default для удобного поиска,
не запрет объединять тесно связанные cases по принятому стилю команды.

### 8.6. Изолированный Unit-тест

Пример отдельной чистой функции `App\Support\Quantity`:

```php
namespace App\Support;

final class Quantity
{
    public static function clamp(int $value, int $maximum): int
    {
        return max(0, min($value, $maximum));
    }
}
```

`tests/Unit/QuantityTest.php` не использует Laravel helpers:

```php
use App\Support\Quantity;

it('ограничивает количество нижней и верхней границами', function (): void {
    expect(Quantity::clamp(-1, 10))->toBe(0)
        ->and(Quantity::clamp(4, 10))->toBe(4)
        ->and(Quantity::clamp(11, 10))->toBe(10);
});
```

### 8.7. Антипримеры и точечный запуск

Плохо: `postJson(...)->assertOk()` для записи без проверки состояния;
mock Action в endpoint-тесте, который должен защищать persistence;
`count() === 1` без проверки какой именно объект пришёл;
`assertSee('title')` вместо семантической проверки JSON.

Команды ниже — стандартные примеры при установленном Pest/Laravel test runner:

```bash
php artisan test --compact tests/Feature/ArticleStoreTest.php
vendor/bin/pest app/Modules/Publishing/Tests/Feature/ArticleIndexTest.php
```

Запускайте путь своего профиля. Container wrapper или task runner может быть
необходим в конкретном окружении. Не запускайте DB-тесты двух приложений на
общем ресурсе одновременно и не выдавайте синтаксическую проверку примеров
за выполненную Feature suite.

### 8.8. Явное обнаружение модульных suites

Пример фрагмента PHPUnit configuration для приложения, где стандартные и
модульные тесты сосуществуют:

```xml
<testsuites>
    <testsuite name="Feature">
        <directory suffix="Test.php">tests/Feature</directory>
        <directory suffix="Test.php">app/Modules/Publishing/Tests/Feature</directory>
    </testsuite>
    <testsuite name="Unit">
        <directory suffix="Test.php">tests/Unit</directory>
        <directory suffix="Test.php">app/Modules/Publishing/Tests/Unit</directory>
    </testsuite>
</testsuites>
```

Для нескольких модулей перечислите пути или используйте поддерживаемый вашей
версией способ discovery. Не предполагайте, что glob из Pest bootstrap имеет
такую же семантику внутри XML. Административные suites включаются аналогично.

Рекомендуемый готовый test style: один behavior на `it(...)`, отдельный case для
каждого существенного отказа, описания на языке проекта, function helpers без
`$this`, один import `use function` на строку и группы по владельцу поведения.
Fixture-классы располагаются отдельно от `*Test.php`. Эти правила можно принять
для обоих профилей, не делая их обязательными свойствами самого Pest.
