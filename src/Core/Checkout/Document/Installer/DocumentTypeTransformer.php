<?php

namespace Dustin\ShopwareUtils\Core\Checkout\Document\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TransformInterface;
use Dustin\ShopwareUtils\Core\Framework\Plugin;

class DocumentTypeTransformer implements TransformInterface
{

    public function transform(string $identifier, array $data, Plugin|AdditionalBundle $bundle): array
    {
        return [
            'technicalName' => $data['technicalName'],
            'fileNamePrefix' => $data['fileNamePrefix'] ?? null,
            'fileNameSuffix' => $data['fileNameSuffix'] ?? null,
            'config' => $data['config'] ?? null,
            'translations' => $this->transformTranslations($data['translations']),
        ];
    }

    public function transformTranslations(array $translations): array
    {
        $result = [];

        foreach($translations as $locale => $data) {
            $translation = [
                'name' => $data['name']
            ];

            if(!empty($data['customFields'])) {
                $translation['customFields'] = $data['customFields'];
            }

            $result[$locale] = $translation;
        }

        return $result;
    }
}