<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Classes\Generators\AudioGenerator\SunoUnofficial\Data\SunoData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SunoRequest extends FormRequest
{
    /** @return array<string, list<string|ValidationRule>|string> */
    public function rules(): array
    {
        return [
            'code' => 'required|int',
            'msg' => 'required|string',
            'data' => 'required|array',
            'data.callbackType' => 'required|string',
            'data.task_id' => 'required|string',
            'data.data' => 'required|array',
            'data.data.*.id' => 'required|string',
            'data.data.*.audio_url' => 'nullable|string',
        ];
    }

    public function toDto(): SunoData
    {
        return SunoData::from($this->validated());
    }
}
