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

        if(!\is_file($constraint->file)) {
            return;
        }

        if(!$constraint->trimContent) {
            if(\filesize($constraint->file) === 0) {
                $this->addEmptyFileViolation($constraint);
            }

            return;
        }

        $content = \file_get_contents($constraint->file);
        $content = \trim($content);

        if(\strlen($content) === 0) {
            $this->addEmptyFileViolation($constraint);
        }
    }

    protected function addEmptyFileViolation(FileNotEmpty $constraint): void
    {
        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ file }}', $constraint->file)
            ->setCode(FileNotEmpty::FILE_NOT_EMPTY_ERROR)
            ->addViolation();
    }
}