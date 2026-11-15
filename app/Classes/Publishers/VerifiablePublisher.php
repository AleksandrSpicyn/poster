<?php

declare(strict_types=1);

namespace App\Classes\Publishers;

use App\Data\VerifiedPublicationData;
use App\Exceptions\PublicationVerificationException;

interface VerifiablePublisher
{
    /** @throws PublicationVerificationException */
    public function verifyPublication(string $externalId): VerifiedPublicationData;
}
