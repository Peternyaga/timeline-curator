<?php

namespace App\Curation;

use DomainException;

class CurationException extends DomainException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct("[$errorCode] $message");
    }
}
