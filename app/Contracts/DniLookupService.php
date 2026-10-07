<?php

namespace App\Contracts;

use App\DTOs\DniPerson;

interface DniLookupService
{
    /**
     * Search citizen information by DNI (8 digits).
     */
    public function search(string $dni): ?DniPerson;
}
