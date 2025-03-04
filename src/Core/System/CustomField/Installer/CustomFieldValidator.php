<?php

namespace Dustin\ShopwareUtils\Core\System\CustomField\Installer;

use Dustin\ShopwareUtils\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CustomFieldValidator
{
    public function __construct(
        protected readonly CustomFieldValidationConstraintFactory $constraintFactory
    ) {}

    public function validate(array $customField, ExecutionContextInterface $context): void
    {
        $type = $customField['type'] ?? null;
        $config = $customField['config'] ?? null;

        // Will be handled by other constraints
        if(!\is_string($type) || !\is_array($config)) {
            return;
        }

        $constraints = $this->constraintFactory->getConfigConstraintsForType($type);

        if($constraints === null) {
            return;
        }

        $violations = $context->getValidator()
            ->startContext()
            ->atPath($context->getPropertyPath().'[config]')
            ->validate($config, $constraints)
            ->getViolations();

        foreach($violations as $violation) {
            $context->getViolations()->add($violation);
        }

        if(\in_array($type, [CustomFieldTypes::INT, CustomFieldTypes::FLOAT], true)) {
            $this->validateMinMax($config, $context);
        }
    }

    protected function validateMinMax(array $config, ExecutionContextInterface $context): void
    {
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
