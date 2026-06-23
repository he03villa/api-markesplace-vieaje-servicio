<?php

namespace App\Exceptions;

class UnauthorizedActionException extends BusinessLogicException
{
    protected $message = 'No tienes permiso para realizar esta accion';
}
