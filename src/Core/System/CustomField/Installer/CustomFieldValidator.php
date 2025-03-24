<?php

namespace Dustin\ShopwareUtils\Core\System\CustomField\Installer;

use Dustin\ShopwareUtils\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CustomFieldValidator
{
    public function __construct(
        protected CustomFieldValidationConstraintFactory $constraintFactory
    ) {}

    public function validate(mixed $customField, ExecutionContextInterface $context): void
    {
        if(!\is_array($customField)) {
            return;
        }

        $type = $customField['type'] ?? null;

        if(!\is_string($type)) {
            return;
        }

        $constraints = $this->constraintFactory->createCustomFieldConstraints($type);

        $violations = $context->getValidator()
            ->startContext()
            ->atPath($context->getPropertyPath())
            ->validate($customField, $constraints)
            ->getViolations();

        foreach($violations as $violation) {
            $context->getViolations()->add($violation);
        }

        if(\in_array($type, [CustomFieldTypes::INT, CustomFieldTypes::FLOAT], true)) {
            $this->validateMinMax($customField, $context);
        }
    }

    protected function validateMinMax(array $customField, ExecutionContextInterface $context): void
    {
        $config = $customField['config'] ?? null;

        if(!\is_array($config)) {
            return;
        }

        $min = $config['min'] ?? null;
        $max = $config['max'] ?? null;

        if(!$this->isIntOrFloat($min) || !$this->isIntOrFloat($max)) {
            return;
        }

        if(!($max > $min)) {
            $violation = new ConstraintViolation(
                'max must be greater than min',
                '',
                [],
                $config,
                $context->getPropertyPath('[config]'),
                $max
            );

            $context->getViolations()->add($violation);
        }
    }

    private function isIntOrFloat(mixed $value): bool
    {
        return \is_int($value) || \is_float($value);
    }
}
