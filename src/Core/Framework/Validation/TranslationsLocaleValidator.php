<?php

declare(strict_types=1);

namespace Dustin\ShopwareUtils\Core\Framework\Validation;

use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TranslationsLocaleValidator
{
    public function validate(?array $translations, ExecutionContextInterface $context): void
    {
        if($translations === null) {
            return;
        }

        $constraints = [new Locale(null, '{{ value }} is not a valid locale.')];

        foreach($translations as $locale => $_translation) {
            $violations = $context->getValidator()
                ->startContext()
                ->atPath($context->getPropertyPath(\sprintf('[%s]', $locale)))
                ->validate($locale, $constraints)
                ->getViolations();

            foreach($violations as $violation) {
                $context->getViolations()->add($violation);
            }
        }
    }
}