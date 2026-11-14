<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\UpdateProfileData;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string|Unique>>
     */
    public function rules(#[CurrentUser] ?User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($user),
            ],
        ];
    }

    public function toDto(): UpdateProfileData
    {
        return UpdateProfileData::from($this->validated());
    }
}
