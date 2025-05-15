<?php

namespace App\Tasks;

use App\Classes\Runway;
use Illuminate\Http\Client\ConnectionException;

readonly class CreateVideo
{
    /* TODO Inject через interface */
    public function __construct(private Runway $runway)
    {
    }

    /**
     * @throws ConnectionException
     */
    public function run(string $imageUrl, string $prompt): string
    {
        return $this->runway->generate($imageUrl, $prompt);
    }
}
