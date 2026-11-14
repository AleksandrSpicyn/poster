<?php

declare(strict_types=1);

namespace App\Http\Requests\Posts;

use App\Data\GetPostsListData;
use App\Enums\ChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class GetPostsListRequest extends FormRequest
{
    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'type' => [
                'nullable',
                'string',
                Rule::in(['All', ...ChannelType::values()]),
            ],
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

    public function toDto(): GetPostsListData
    {
        return GetPostsListData::from(array_filter($this->validated(), static fn(mixed $value): bool => $value !== null));
    }
}
