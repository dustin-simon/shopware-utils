<?php

namespace Dustin\ShopwareUtils\Core\System\CustomField\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Dustin\ShopwareUtils\Core\Framework\Validation\TranslationsLocaleValidator;
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
    protected CustomFieldValidator $customFieldValidator;

    public function __construct(
        ?CustomFieldValidator $customFieldValidator = null,
        TranslationsLocaleValidator $localeValidator = new TranslationsLocaleValidator()
    ) {
        $this->customFieldValidator = $customFieldValidator ?? new CustomFieldValidator($this);

        parent::__construct($localeValidator);
    }

    public function createConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint
    {
        return new Collection(
            [
                'name' => [new NotBlank(), new Type('string'), new IdenticalTo($identifier)],
                'label' => $this->createTranslationConstraints(false),
                'translated' => [new NotNull(), new Type('bool')],
                'editable' => self::optional([new Type('bool')]),
                'position' => self::optional([new Type('integer')]),
                'customFields' => [
                    new NotBlank(),
                    new Type('associative_array'),
                    new All([
                        new NotBlank(),
                        new Type('associative_array'),
                        new Callback([$this->customFieldValidator, 'validate'])
                    ])
                ],
                'relations' => self::optional([new NotBlank(), new All([new Type('string')])]),
            ],
            null, null,
            false
        );
    }

    public function createCustomFieldConstraints(string $type): array
    {
        $constraints = [
            'type' => [new NotBlank(), new Type('string'), new Choice([], CustomFieldTypes::TYPES)],
            'allowStoreApiWrite' => self::optional([new Type('bool')]),
            'allowCartExpose' => self::optional([new Type('bool')]),
            'position' => self::optional([new Type('integer')]),
            'label' => $this->createTranslationConstraints(false),
            'helpText' => $this->createTranslationConstraints(true),
            'required' => self::optional([new Type('bool')]),
        ];

        $constraints = \array_merge(
            $constraints,
            $this->createConstraintsForType($type)
        );

        return [new Collection($constraints, null, null, false)];
    }

    public function createConstraintsForType(string $type): array
    {
        return match ($type) {
            CustomFieldTypes::TEXT => $this->createTextTypeConstraints(),
            CustomFieldTypes::ENTITY => $this->createEntityTypeConstraints(),
            CustomFieldTypes::FLOAT => $this->createNumberTypeConstraints('float'),
            CustomFieldTypes::INT => $this->createNumberTypeConstraints('int'),
            CustomFieldTypes::BOOL => $this->createBoolTypeConstraints(),
            CustomFieldTypes::SELECT => $this->createSelectTypeConstraints(),
            CustomFieldTypes::DATETIME => $this->createDatetimeTypeConstraints(),
            CustomFieldTypes::JSON => $this->createJsonTypeConstraints(),
            CustomFieldTypes::HTML => $this->createHtmlTypeConstraints(),
            default => [],
        };
    }

    protected function createTextTypeConstraints(): array
    {
        return [
            'placeholder' => $this->createTranslationConstraints(true),
            'config' => $this->config(['large' => self::optional([new Type('bool')])], true)
        ];
    }

    protected function createEntityTypeConstraints(): array
    {
        return [
            'placeholder' => $this->createTranslationConstraints(true),
            'config' => $this->config([
                'entity' => [new NotBlank(), new Type('string')],
                'multiselect' => [new NotNull(), new Type('bool')],
            ])
        ];
    }

    protected function createNumberTypeConstraints(string $type): array
    {
        $config = [
            'max' => self::optional([new Type($type)]),
            'min' => self::optional([new Type($type)]),
            'step' => self::optional([new Type($type), new Positive()]),
            'allowEmpty' => self::optional([new Type('bool')]),
        ];

        if($type === CustomFieldTypes::FLOAT) {
            $config['digits'] = self::optional([new Type('integer'), new Positive()]);
        }

        return [
            'placeholder' => $this->createTranslationConstraints(true),
            'config' => $this->config($config, true)
        ];
    }

    protected function createBoolTypeConstraints(): array
    {
        return [
            'config' => $this->config([
                'component' => [new NotBlank(), new Type('string'), new Choice([], ['checkbox', 'switch'])]
            ])
        ];
    }

    protected function createSelectTypeConstraints(): array
    {
        return [
            'placeholder' => $this->createTranslationConstraints(true),
            'config' => $this->config([
                'multiselect' => [new NotNull(), new Type('bool')],
                'options' => [
                    new NotBlank(),
                    new Type('list'),
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
            ])
        ];
    }

    protected function createDatetimeTypeConstraints(): array
    {
        return [
            'placeholder' => $this->createTranslationConstraints(true),
        ];
    }

    protected function createJsonTypeConstraints(): array
    {
        return [
            'placeholder' => $this->createTranslationConstraints(true)
        ];
    }

    protected function createHtmlTypeConstraints(): array
    {
        return [
            'placeholder' => $this->createTranslationConstraints(true)
        ];
    }

    protected function createTranslationConstraints(bool $optional): array|Constraint
    {
        $constraints = [
            new NotBlank(),
            new Callback([$this->localeValidator, 'validate']),
            new Collection(
                [
                    'en-GB' => [],
                    'de-DE' => [],
                ],
                null, null,
                true
            ),
            new All(['constraints' => [
                new NotBlank(),
                new Type('string')
            ]])
        ];

        if($optional) {
            return self::optional($constraints, true);
        }

        return $constraints;
    }

    protected function config(array $constraints, bool $optional = false): array|Constraint
    {
        $constraints = [
            new NotBlank(),
            new Type('associative_array'),
            new Collection($constraints, null, null, false)
        ];

        if($optional) {
            return self::optional($constraints);
        }

        return $constraints;
    }
}
