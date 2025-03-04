<?php

namespace Dustin\ShopwareUtils\Core\Framework\Installer\Exception;

use Dustin\ShopwareUtils\Core\Framework\Exception\ConstraintViolationException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class FileValidationFailedException extends InstallException
{
    public function __construct(
        string $file,
        ConstraintViolationListInterface $violations,
        array $input
    ) {
        parent::__construct(
            \sprintf('Validation of file %s failed.', $file),
            0,
            new ConstraintViolationException($violations, $input)
        );
    }
}