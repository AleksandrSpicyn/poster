<?php

namespace App\Http\Requests\Prompts;

use App\Data\GetPromptsListData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetPromptsListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
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
        return GetPromptsListData::from($this);
    }
}
