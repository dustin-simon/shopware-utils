<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TransformInterface;
use Dustin\ShopwareUtils\Core\Framework\Plugin;

class MailTemplateTransformer implements TransformInterface
{

    public function transform(string $identifier, array $data, AdditionalBundle|Plugin $bundle): array
    {
        return [
            'id' => $data['id'],
            'type' => $data['type'],
            'translations' => $this->transformTranslations($identifier, $data['translations'], $bundle)
        ];
    }

    public function transformTranslations(string $identifier, array $translations, AdditionalBundle|Plugin $bundle): array
    {
        $result = [];

        /** @var array $translation */
        foreach($translations as $locale => $data) {
            $translation = [
                'senderName' => $data['senderName'] ?? '',
                'description' => $data['description'] ?? '',
                'subject' => $data['subject'],
                'contentPlain' => $this->getTranslatedContent($identifier, $locale, false, $bundle),
                'contentHtml' => $this->getTranslatedContent($identifier, $locale, true, $bundle),
            ];

            if(!empty($data['customFields'])) {
                $translation['customFields'] = $data['customFields'];
            }

            $result[$locale] = $translation;
        }

        return $result;
    }

    protected function getTranslatedContent(string $identifier, string $locale, bool $html, AdditionalBundle|Plugin $bundle): string
    {
        return \file_get_contents(MailTemplateUtil::getContentFile($identifier, $locale, $html, $bundle));
    }
}