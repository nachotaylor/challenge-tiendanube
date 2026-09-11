<?php

namespace App\Exceptions;

use RuntimeException;

class NumeratorConflictException extends RuntimeException
{
    public function __construct(public readonly int $currentNumerator)
    {
        parent::__construct("Numerator does not match the expected old value (current: {$currentNumerator}).");
    }
}
