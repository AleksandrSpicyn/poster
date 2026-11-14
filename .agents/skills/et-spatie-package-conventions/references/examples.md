# Package Examples With Explicit Versions

## Laravel Data v4

```php
namespace App\Dto;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

final class StoreArticleData extends Data
{
    public function __construct(
        #[Required, StringType, Max(200)]
        public string $title,
    ) {}
}
```

Standard location: `app/Dto`. Modular location:
`app/Modules/Publishing/Dto`, with matching namespace. Validate at the supported
request boundary or explicitly; `from()` is not an unconditional validation
promise for every configuration. Output Data serialization is supported by the
package; the recommended layered profile uses Resources for stable HTTP output
when that is the application's chosen serializer boundary.

Source: [Data v4 validation](https://spatie.be/docs/laravel-data/v4/validation/using-validation-attributes).

## Media Library v11

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

Configure the `private` disk and authorized download path separately. The name
alone is not security. Keep package replacement/cleanup operations intact;
do not rewrite package relations as raw persistence for logging convenience.

Source: [Media Library v11 collections](https://spatie.be/docs/laravel-medialibrary/v11/working-with-media-collections/defining-media-collections).

## Permission and Settings Review

For supported Permission versions, retain `assignRole`, `givePermissionTo` and
policy/Gate integration instead of raw pivot writes. Verify guards and cache.
For Settings, show the typed settings class and group, persisted defaults,
migrations, discovery paths and fresh-worker behavior together; a cache command
alone does not prove default values or eliminate stale persistent instances.
