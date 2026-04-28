<?php

namespace App\Exceptions;

use RuntimeException;

class ChequeClearingAccountRequired extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('A Cheque Clearing treasury account is required before registering receivable cheques. Please create one first.'));
    }
}
