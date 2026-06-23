<?php

namespace App\Exceptions;

class CannotReviewYourselfException extends BusinessLogicException
{
    protected $message = 'No puedes calificarte a ti mismo';
}
