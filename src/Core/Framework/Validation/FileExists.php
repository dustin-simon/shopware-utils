<?php

namespace Dustin\ShopwareUtils\Core\Framework\Validation;

use Symfony\Component\Validator\Constraint;

class FileExists extends Constraint
{
    public const FILE_NOT_EXISTS_ERROR = 'b6904050-d5e0-46b8-9293-0c41d1154e92';

    protected const ERROR_NAMES = [
        self::FILE_NOT_EXISTS_ERROR => 'FILE_NOT_EXISTS_ERROR',
    ];

    public string $message = 'File {{ file }} does not exist.';

    public function __construct(
        public ?string $file = null,
        public ?string $baseDir = null,
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);
    }
}