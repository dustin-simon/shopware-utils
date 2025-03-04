<?php

namespace Dustin\ShopwareUtils\Core\Framework\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Symfony\Component\Validator\Constraint;

interface TranslationConstraintFactoryInterface
{
    public function createTranslationConstraints(AdditionalBundle|Plugin $bundle, string $identifier): array|Constraint;
}