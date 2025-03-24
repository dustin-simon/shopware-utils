<?php

namespace Dustin\ShopwareUtils\Core\Checkout\Document\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TranslationConstraintFactoryInterface;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class DocumentTypeValidationConstraintFactory extends ValidationConstraintFactory implements TranslationConstraintFactoryInterface
{

    public function createConstraints(Plugin|AdditionalBundle $bundle, string $identifier): array|Constraint
    {
        return new Collection(
            [
                'technicalName' => [new NotBlank(), new Type('string'), new IdenticalTo($identifier)],
                'fileNamePrefix' => self::optional([new NotBlank(), new Type(['string'])]),
                'fileNameSuffix' => self::optional([new NotBlank(), new Type(['string'])]),
                'config' => self::optional([new Type('array')]),
                'translations' => $this->createTranslationsConstraints($bundle, $identifier, $this)
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
                'customFields' => $this->createCustomFieldsConstraints()
            ],
            null, null,
            false
        );
    }
}