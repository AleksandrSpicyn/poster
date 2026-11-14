# PHP Style Examples

Ready-to-adopt conventions: strict comparisons, short arrays, typed methods,
meaningful PHPDoc, constructor promotion, explicit branch blocks and enum cases
in UPPER_SNAKE_CASE. Read formatter config before applying them to an existing
repository. File layout does not change these rules.

```php
namespace App\Support;

final class ArticleSummary
{
    /** @return array{title: string, is_published: bool} */
    public static function make(string $title, bool $published): array
    {
        if ($title === '') {
            return ['title' => 'Без заголовка', 'is_published' => false];
        }

        return ['title' => $title, 'is_published' => $published];
    }
}
```

An obvious `@return string` on a method already returning string adds little;
this array shape communicates an otherwise hidden contract.

## Verification Examples

When installed/configured in the target project:

```bash
vendor/bin/pint --dirty
vendor/bin/phpstan analyse
php artisan test --compact tests/Feature/ArticleStoreTest.php
```

Use a module test path for the modular profile. Use repository wrappers when
they provide containers/environment. Do not assume Make targets, a PHPStan level
or a Rector set from another application. Review fixer changes; formatting
success is not runtime proof.
