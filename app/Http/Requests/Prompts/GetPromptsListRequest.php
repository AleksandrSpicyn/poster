<?php

declare(strict_types=1);

namespace App\Http\Requests\Prompts;

use App\Data\GetPromptsListData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class GetPromptsListRequest extends FormRequest
{
    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in(['asc', 'desc']),
            ],
        ];
    }

    public function toDto(): GetPromptsListData
    {
        return GetPromptsListData::from(array_filter($this->validated(), static fn(mixed $value): bool => $value !== null));
    }
}
