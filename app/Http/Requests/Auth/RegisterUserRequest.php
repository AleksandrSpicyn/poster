<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Data\RegisterUserData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class RegisterUserRequest extends FormRequest
{
    /** @return array<string, list<string|Password|Unique>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)], 'password' => ['required', 'confirmed', Password::defaults()]];
    }

    public function toDto(): RegisterUserData
    {
        return RegisterUserData::from($this->validated());
    }
}
