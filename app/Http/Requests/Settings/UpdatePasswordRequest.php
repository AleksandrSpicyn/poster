<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    /** @return array<string, list<string|Password>> */
    public function rules(): array
    {
        return ['current_password' => ['bail', 'required', 'string', 'current_password'], 'password' => ['required', Password::defaults(), 'confirmed']];
    }
}
