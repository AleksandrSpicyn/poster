# Activity Logging Examples

## Choosing the Mutation Path

| Path | Required decision |
|---|---|
| Eloquent save/delete | Does a trait/observer already log it? Avoid duplicates |
| Child/pivot | Child subject or parent aggregate? Retain relation identity |
| Sync | Preserve domain/package method; inspect resulting normalized state |
| Bulk/upsert | Events may be bypassed; scope snapshots by the complete key |
| Media replacement | Preserve cleanup/events and log the actual outcome |
| Identical old/new state | Suppress a change record when no-op policy requires it |

## Actual Package Example: Spatie Activitylog v5

Assume `$article` and `$actor` are models and the export business event is not
already automatically logged:

```php-fragment
activity()
    ->performedOn($article)
    ->causedBy($actor)
    ->withProperties(['source' => 'console'])
    ->event('publication_exported')
    ->log('Выполнен экспорт публикации');
```

`properties` is custom metadata; in v5 it is separate from model
`attribute_changes`. Check the installed major version before assuming schema.
Source: [Activitylog v5 logging](https://spatie.be/docs/laravel-activitylog/v5/basic-usage/logging-activity).

## Application Abstractions Are Not Package APIs

A project may supply context scopes, parent traits, activity-aware sync/bulk
methods or no-op normalization. Inspect their real signatures and guarantees.
Do not import those names from another application or treat them as Laravel APIs.
A custom audit contract should explicitly define actor/source, subject/diff,
transaction behavior, tenant scope and secret exclusion.

## Regression Assertion

After factory setup, capture an activity watermark. Perform exactly one mutation
and inspect only new records. Assert subject, event, actor/source and changed
fields; repeat the same state and assert no extra change record. Test rollback
according to the integration's actual transaction/channel contract.

In both layouts the test belongs to the mutation owner: `tests/Feature` or the
module's `Tests/Feature`. Do not assert the last global activity without scoping;
fixture/parallel work can create unrelated records.
