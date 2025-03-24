<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TransformInterface;
use Dustin\ShopwareUtils\Core\Framework\Plugin;

class MailTemplateTypeTransformer implements TransformInterface
{
    public function transform(string $identifier, array $data, AdditionalBundle|Plugin $bundle): array
    {
        return [
            'technicalName' => $data['technicalName'],
            'availableEntities' => $data['availableEntities'] ?? null,
            'templateData' => $data['templateData'] ?? null,
            'translations' => $this->transformTranslations($data['translations']),
        ];
    }

    public function transformTranslations(array $translations): array
    {
        $result = [];

        foreach($translations as $locale => $data) {
            $translation = [
                'name' => $data['name'],
            ];

            if(!empty($data['customFields'])) {
                $translation['customFields'] = $data['customFields'];
            }

            $result[$locale] = $translation;
        }

        return $result;
    }
}