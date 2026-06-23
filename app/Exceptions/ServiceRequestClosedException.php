<?php

namespace App\Exceptions;

class ServiceRequestClosedException extends BusinessLogicException
{
    protected $message = 'Esta solicitud ya no está abierta';
}
