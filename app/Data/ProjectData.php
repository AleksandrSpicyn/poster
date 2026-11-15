<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class ProjectData extends Data
{
    public const int NAME_MAX_LENGTH = 255;

    public const int PROMPT_MAX_LENGTH = 10000;

    public function __construct(
        #[Required, Max(self::NAME_MAX_LENGTH)]
        public string $name,
        #[Required, Max(self::PROMPT_MAX_LENGTH)]
        public string $prompt,
    ) {}

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'name.required' => 'Enter a project name.',
            'name.string' => 'Enter a project name.',
            'prompt.required' => 'Describe the creative direction for this project.',
            'prompt.string' => 'Describe the creative direction for this project using text.',
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return ['name' => 'project name', 'prompt' => 'base prompt'];
    }
}
