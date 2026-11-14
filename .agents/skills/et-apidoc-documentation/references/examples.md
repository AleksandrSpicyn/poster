# Scribe Examples

Standard profile: docblock on a controller under `app/Http/Controllers`.
Modular profile: the same contract under the module's `Controllers` directory.
The route must be included by the generator's real route-selection config.

For Scribe 5.x, document actual inputs and responses, for example:

```php
/**
 * Создать публикацию
 *
 * Создаёт публикацию текущего пользователя.
 *
 * @group Публикации
 * @authenticated
 * @header Accept application/json
 * @bodyParam title string required Заголовок до 200 символов. Example: Пример
 * @bodyParam is_published boolean required Признак публикации. Example: false
 * @response 201 scenario="Публикация создана" {
 *   "data": {"id":7,"title":"Пример","is_published":false}
 * }
 * @responseField data object Созданная публикация.
 * @responseField data.id integer Идентификатор публикации.
 * @responseField data.title string Заголовок.
 * @responseField data.is_published boolean Признак публикации.
 */
```

This example assumes that exact response; it does not prescribe an envelope for
every API. Add nested author fields only if that endpoint actually emits them.
Document production error bodies, not debug traces or a neighboring handler's
JSON. Each response example should carry a meaningful scenario.

## Nested Input and Safe File Examples

For a separate multipart/batch endpoint:

```php
/**
 * @bodyParam items object[] required Элементы операции.
 * @bodyParam items[].title string required Заголовок. Example: Пример
 * @bodyParam attachment file PDF до 5 МБ. No-example
 */
```

Do not write `Example: document.pdf` unless it is an intended real file fixture.
Keep inline `Example:` on the annotation line; recommended line width is 100
characters except that inline example. Make long JSON examples multiline.

`Enum:` is supported by current Scribe tags for closed sets; custom `min=`,
`format=` parsing is a separate extension. Do not strip constraints from the
specification: express them with supported extraction/attributes/schema overrides
or an explicitly documented extension, then inspect generated OpenAPI.

Reference grammar: [Scribe annotations](https://scribe.knuckles.wtf/laravel/reference/annotations).
