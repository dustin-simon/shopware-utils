<?php

namespace Dustin\ShopwareUtils\Core\Framework\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FileExistsValidator extends ConstraintValidator
{

    public function validate(mixed $value, Constraint $constraint): void
    {
        if(!$constraint instanceof FileExists) {
            throw new UnexpectedTypeException($constraint, FileExists::class);
        }

        $file = $constraint->file ?? $value;

        if(!\is_string($file) || empty($file)) {
            return;
        }

        $baseDir = $constraint->baseDir ?? '';

        if(!empty($baseDir)) {
            $file = \rtrim($baseDir, '/').'/'.\ltrim($file, '/');
        }

        if(!\is_file($file)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ file }}', $file)
                ->setCode(FileExists::FILE_NOT_EXISTS_ERROR)
                ->addViolation();
        }
    }
}