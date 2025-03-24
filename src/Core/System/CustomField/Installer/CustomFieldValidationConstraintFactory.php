<?php

namespace Dustin\ShopwareUtils\Core\System\CustomField\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Dustin\ShopwareUtils\Core\System\CustomField\CustomFieldTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Type;

class CustomFieldValidationConstraintFactory extends ValidationConstraintFactory
{
    public function createConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint
    {
        return new Collection(
            [
                'name' => [new NotBlank(), new Type('string'), new IdenticalTo($identifier)],
                'config' => new Collection([
                    'label' => $this->createTranslationConstraints(false),
                    'translated' => [new NotNull(), new Type('bool')],
                ], null, null, true),
                'global' => self::optional([new Type('bool')]),
                'position' => self::optional([new Type('integer')]),
                'customFields' => [new NotBlank(), new All($this->createCustomFieldConstraints())],
                'relations' => [new NotBlank(), new All([new Type('string')])],
            ],
            null, null,
            false
        );
    }

    public function createCustomFieldConstraints(): array|Constraint
    {
        return [
            new Collection(
                [
                    'name' => [new NotBlank(), new Type('string')],
                    'type' => [new NotBlank(), new Type('string'), new Choice([], CustomFieldTypes::TYPES)],
                    'allowCustomerWrite' => self::optional([new Type('bool')]),
                    'allowCartExpose' => self::optional([new Type('bool')]),
                    'config' => [new NotBlank(), new Type('array')],
                ],
                null, null,
                false
            ),
            new Callback([new CustomFieldValidator($this), 'validate']),
        ];
    }

    public function getConfigConstraintsForType(string $type): array|Constraint|null
    {
        $fields = [
            'position' => self::optional([new Type('integer')]),
            'label' => $this->createTranslationConstraints(false),
            'helpText' => $this->createTranslationConstraints(true),
            'placeholder' => $this->createTranslationConstraints(true),
            'required' => self::optional([new Type('bool')]),
        ];

        switch($type) {
            case CustomFieldTypes::TEXT:
                $fields = $this->mergeTextTypeConfigConstraints($fields);
                break;
            case CustomFieldTypes::ENTITY:
                $fields = $this->mergeEntityTypeConfigConstraints($fields);
                break;
            case CustomFieldTypes::FLOAT:
                $fields = $this->mergeNumberTypeConfigConstraints($fields, 'float');
                break;
            case CustomFieldTypes::INT:
                $fields = $this->mergeNumberTypeConfigConstraints($fields, 'int');
                break;
            case CustomFieldTypes::BOOL:
                $fields = $this->mergeBoolTypeConfigConstraints($fields);
                break;
            case CustomFieldTypes::SELECT:
                $fields = $this->mergeSelectTypeConfigConstraints($fields);
                break;
        }

        return [
            new Collection($fields, null, null, false),
        ];
    }

    protected function createTranslationConstraints(bool $optional): array|Constraint
    {
        $constraints = [
            new NotBlank(),
            new Collection(
                [
                    'en-GB' => [new NotBlank(), new Type('string')],
                    'de-DE' => [new NotBlank(), new Type('string')],
                ],
                null, null,
                true
            ),
        ];

        if($optional) {
            return self::optional($constraints, true);
        }

        return $constraints;
    }

    protected function mergeTextTypeConfigConstraints(array $fields): array
    {
        return \array_merge(
            $fields,
            [
                'large' => self::optional([new Type('bool')]),
            ]
        );
    }

    protected function mergeEntityTypeConfigConstraints(array $fields): array
    {
        return \array_merge(
            $fields,
            [
                'entity' => [new NotBlank(), new Type('string')],
                'multiselect' => [new NotNull(), new Type('bool')],
            ],
        );
    }

    protected function mergeNumberTypeConfigConstraints(array $fields, string $type): array
    {
        $fields = \array_merge(
            $fields,
            [
                'max' => self::optional([new Type($type)]),
                'min' => self::optional([new Type($type)]),
                'step' => self::optional([new Type($type), new Positive()]),
                'allowEmpty' => self::optional([new Type('bool')]),
            ],
        );

        if($type === CustomFieldTypes::FLOAT) {
            $fields['digits'] = self::optional([new Type('integer'), new Positive()]);
        }

        return $fields;
    }

    protected function mergeBoolTypeConfigConstraints(array $fields): array
    {
        return \array_merge(
            $fields,
            [
                'component' => self::optional([new NotBlank(), new Type('string'), new Choice([], ['checkbox', 'switch'])]),
            ],
        );
    }

    protected function mergeSelectTypeConfigConstraints(array $fields): array
    {
        return \array_merge(
            $fields,
            [
                'multiselect' => [new NotNull(), new Type('bool')],
                'options' => [
                    new NotBlank(),
                    new All(
                        new Collection(
                            [
                                'value' => [new NotBlank(), new Type('string')],
                                'label' => $this->createTranslationConstraints(false),
                            ],
                            null, null,
                            false
                        )
                    ),
                ],
            ],
        );
    }
}
