<?php

namespace App\Exceptions;

use Exception;

class BusinessLogicException extends Exception
{
    protected $code = 400;
}

class InsufficientSeatsException extends BusinessLogicException
{
    protected $message = 'No hay suficientes asientos disponibles';
}

class CannotJoinOwnRideException extends BusinessLogicException
{
    protected $message = 'No puedes unirte a tu propio viaje';
}

class ServiceRequestClosedException extends BusinessLogicException
{
    protected $message = 'Esta solicitud ya no está abierta';
}

class CannotReviewYourselfException extends BusinessLogicException
{
    protected $message = 'No puedes calificarte a ti mismo';
}

class UnauthorizedActionException extends BusinessLogicException
{
    protected $message = 'No tienes permiso para realizar esta accion';
}
