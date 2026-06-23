<?php

namespace App\Utils;

use L5Swagger\CustomGeneratorInterface;
use OpenApi\Generator;
use Psr\Log\AbstractLogger;

class SwaggerCustomGenerator implements CustomGeneratorInterface
{
    public function create(): Generator
    {
        return new Generator(new class extends AbstractLogger
        {
            public function log($level, $message, array $context = []): void
            {
            }
        });
    }
}
