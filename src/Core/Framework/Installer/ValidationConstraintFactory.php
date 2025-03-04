<?php

namespace Dustin\ShopwareUtils\Core\Framework\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Dustin\ShopwareUtils\Core\Framework\Validation\TranslationsLocaleValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Optional;
use Symfony\Component\Validator\Constraints\Type;

abstract class ValidationConstraintFactory
{
    public function __construct(
        protected TranslationsLocaleValidator $localeValidator = new TranslationsLocaleValidator(),
    ) {}

    abstract public function createConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint;

    protected static function optional(array $constraints, bool $allowNull = false): Optional
    {
        if(!$allowNull) {
            \array_unshift($constraints, new NotNull());
        }

        return new Optional([
            'constraints' => $constraints,
        ]);
    }

    public function createTranslationsConstraints(AdditionalBundle|Plugin $bundle, string $identifier, TranslationConstraintFactoryInterface $translationConstraint): array
    {
        return [
            new Callback([$this->localeValidator, 'validate']),
            new Collection(
                [
                    'de-DE' => [],
                    'en-GB' => [],
                ],
                null, null,
                true
            ),
            new All(['constraints' => $translationConstraint->createTranslationConstraints($bundle, $identifier)])
        ];
    }

    public function createCustomFieldsConstraints(): array|Constraint
    {
        return self::optional([new Type('associative_array')]);
    }
}