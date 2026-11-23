<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Data\VideoUsageData;
use UnexpectedValueException;

class MalformedVideoResultException extends UnexpectedValueException
{
    public function __construct(UnexpectedValueException $previous, public readonly VideoUsageData $usage)
    {
        parent::__construct($previous->getMessage(), previous: $previous);
    }
}
