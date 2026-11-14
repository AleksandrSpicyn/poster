<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Data\ResetPasswordData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /** @return array<string, list<string|Password>> */
    public function rules(): array
    {
        return ['token' => ['required'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', Password::defaults()]];
    }

    public function toDto(): ResetPasswordData
    {
        return ResetPasswordData::from($this->validated());
    }
}
