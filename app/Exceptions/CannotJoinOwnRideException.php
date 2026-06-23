<?php

namespace App\Exceptions;

class CannotJoinOwnRideException extends BusinessLogicException
{
    protected $message = 'No puedes unirte a tu propio viaje';
}
