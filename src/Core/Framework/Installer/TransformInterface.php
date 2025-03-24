<?php

namespace Dustin\ShopwareUtils\Core\Framework\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Plugin;

interface TransformInterface
{
    public function transform(string $identifier, array $data, AdditionalBundle|Plugin $bundle): array;
}