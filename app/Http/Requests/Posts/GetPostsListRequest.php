<?php

namespace App\Http\Requests\Posts;

use App\Data\GetPostsListData;
use App\Enums\ChannelType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetPostsListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
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
        return GetPostsListData::from($this);
    }
}
