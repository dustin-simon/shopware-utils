<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TranslationConstraintFactoryInterface;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class MailTemplateTypeValidationConstraintFactory extends ValidationConstraintFactory implements TranslationConstraintFactoryInterface
{
    public function createConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint
    {
        return new Collection(
            [
                'technicalName' => [new NotBlank(), new Type('string'), new IdenticalTo($identifier)],
                'availableEntities' => self::optional([
                    new Type('array'),
                    new All([
                        new NotBlank(null, null, true),
                        new Type(['string', 'null'])
                    ])
                ], true),
                'templateData' => self::optional([new Type('array')], true),
                'translations' => $this->createTranslationsConstraints($bundle, $identifier, $this)
            ],
            null, null,
            false
        );
    }

    public function createTranslationConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint
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