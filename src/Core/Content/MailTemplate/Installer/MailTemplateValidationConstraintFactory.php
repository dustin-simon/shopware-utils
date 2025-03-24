<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TranslationConstraintFactoryInterface;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class MailTemplateValidationConstraintFactory extends ValidationConstraintFactory implements TranslationConstraintFactoryInterface
{

    public function createConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint
    {
        return new Collection(
            [
                'id' => [new NotBlank(), new Type('string'), new Uuid()],
                'name' => [new NotBlank(), new Type('string'), new IdenticalTo($identifier)],
                'type' => [new NotBlank(), new Type('string')],
                'systemDefault' => self::optional([new Type('bool')]),
                'translations' => $this->createTranslationsConstraints($bundle, $identifier, $this)
            ],
            null, null,
            false
        );
    }

    public function createTranslationConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint
    {
        return [
            new Callback([new MailTemplateTranslationValidator($bundle, $identifier), 'validate']),
            new Collection(
                [
                    'senderName' => self::optional([new NotBlank(), new Type('string')]),
                    'description' => self::optional([new NotBlank(), new Type('string')]),
                    'subject' => [new NotBlank(), new Type('string')],
                    'customFields' => $this->createCustomFieldsConstraints()
                ],
                null, null,
                false
            )
        ];
    }
}