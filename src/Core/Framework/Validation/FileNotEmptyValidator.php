<?php

namespace Dustin\ShopwareUtils\Core\Framework\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FileNotEmptyValidator extends ConstraintValidator
{

    public function validate(mixed $value, Constraint $constraint)
    {
        if(!$constraint instanceof FileNotEmpty) {
            throw new UnexpectedTypeException($constraint, FileNotEmpty::class);
        }

        $file = $constraint->file ?? $value;

        if(!\is_string($file) || !\is_file($file)) {
            return;
        }

        if(!$constraint->trimContent) {
            if(\filesize($file) === 0) {
                $this->addEmptyFileViolation($constraint, $file);
            }

            return;
        }

        $content = \file_get_contents($file);
        $content = \trim($content);

        if(\strlen($content) === 0) {
            $this->addEmptyFileViolation($constraint, $file);
        }
    }

    protected function addEmptyFileViolation(FileNotEmpty $constraint, string $file): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ file }}', $file)
            ->setCode(FileNotEmpty::FILE_NOT_EMPTY_ERROR)
            ->addViolation();
    }
}