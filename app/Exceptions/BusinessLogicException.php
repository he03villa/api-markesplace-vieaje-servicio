<?php

namespace App\Exceptions;

use Exception;

class BusinessLogicException extends Exception
{
    protected $code = 400;
}
