<?php
declare(strict_types=1);
namespace App\Core;

class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors, string $message = 'Certaines informations sont invalides.')
    {
        parent::__construct($message);
    }
}
