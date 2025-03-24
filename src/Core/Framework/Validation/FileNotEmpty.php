<?php

namespace Dustin\ShopwareUtils\Core\Framework\Validation;

use Symfony\Component\Validator\Constraint;

class FileNotEmpty extends Constraint
{
    public const FILE_NOT_EMPTY_ERROR = '14f84d19-5a78-4c91-9461-090700dc424e';

    protected const ERROR_NAMES = [
        self::FILE_NOT_EMPTY_ERROR => 'FILE_NOT_EMPTY_ERROR',
    ];

    public string $message = 'File {{ file }} is empty.';

    public bool $trimContent = false;

    public function __construct(public ?string $file = null, mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);
    }
}