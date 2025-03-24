<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Plugin;

class MailTemplateUtil
{
    public static function getContentFile(string $identifier, string $locale, bool $html, AdditionalBundle|Plugin $bundle): string
    {
        $dir = \rtrim($bundle->getMailTemplatesPath(), '/');
        $pattern = '%s/%s.%s%s.twig';
        $params = [$dir, $identifier, $locale, $html === true ? '.html' : ''];

        return \sprintf($pattern, ...$params);
    }
}