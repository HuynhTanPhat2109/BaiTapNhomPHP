<?php

namespace App\Services;

final class ValidationException extends \RuntimeException
{
    public array $errors;

    public function __construct(array $errors)
    {
        parent::__construct('Dữ liệu không hợp lệ.');
        $this->errors = $errors;
    }
}
