<?php

namespace App\Exceptions;

class InsufficientSeatsException extends BusinessLogicException
{
    protected $message = 'No hay suficientes asientos disponibles';
}
