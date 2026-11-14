# Validation Examples

Standard path: `app/Http/Requests/StoreArticleBatchRequest.php`. Modular path:
`app/Modules/Publishing/Requests/StoreArticleBatchRequest.php`.

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreArticleBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.title' => ['required', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['items.*.title' => 'заголовок публикации'];
    }
}
```

The locale is illustrative. Repeated translations belong in the configured
validation language file; an uncommon alias can remain with the validator.
Do not create per-index translations such as `items.0.title` for every entry.

## Important Boundary Cases

- `nullable` may skip the custom rule you expected on missing/null input.
- Casting malformed import input before validation may hide the original error.
- Scoped `exists` validates an input ID but does not authorize the operation.
- Domain conflict is not automatically a request-validation `422`.
- Feature tests assert failing field keys and no write; exact custom-rule wording
  gets isolated coverage when that message is the contract under change.
