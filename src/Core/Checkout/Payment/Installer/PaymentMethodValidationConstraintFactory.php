<?php

namespace Dustin\ShopwareUtils\Core\Checkout\Payment\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TranslationConstraintFactoryInterface;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Dustin\ShopwareUtils\Core\Framework\Validation\FileExists;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class PaymentMethodValidationConstraintFactory extends ValidationConstraintFactory implements TranslationConstraintFactoryInterface
{

    public function createConstraints(Plugin|AdditionalBundle $bundle, string $identifier): array|Constraint
    {
        return new Collection(
            [
                'technicalName' => [new NotBlank(), new Type('string'), new IdenticalTo($identifier)],
                'handlerIdentifier' => self::optional([new NotBlank(), new Type('string')]),
                'position' => self::optional([new Type('int')]),
                'active' => self::optional([new Type('bool')]),
                'afterOrderEnabled' => self::optional([new Type('bool')]),
                'media' => self::optional([new NotBlank(), new Type(['string']), new FileExists(null, $bundle->getPaymentMethodsPath())]),
                'translations' => $this->createTranslationsConstraints($bundle, $identifier, $this),
            ],
            null, null,
            false
        );
    }

    public function createTranslationConstraints(Plugin|AdditionalBundle $bundle, string $identifier): array|Constraint
    {
        return new Collection(
            [
                'name' => [new NotBlank(), new Type('string')],
                'description' => self::optional([new NotBlank(), new Type('string')]),
                'customFields' => $this->createCustomFieldsConstraints()
            ],
            null, null,
            false
        );
    }
}