<?php

namespace Dustin\ShopwareUtils\Core\Checkout\Payment\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TransformInterface;
use Dustin\ShopwareUtils\Core\Framework\Plugin;

class PaymentMethodTransformer implements TransformInterface
{

    public function transform(string $identifier, array $data, Plugin|AdditionalBundle $bundle): array
    {
        $paymentMethod = [
            'technicalName' => $data['technicalName'],
            'translations' => $this->transformTranslations($data['translations']),
        ];

        foreach(['handlerIdentifier', 'position', 'active', 'afterOrderEnabled', 'media'] as $field) {
            if(!empty($data[$field])) {
                $paymentMethod[$field] = $data[$field];
            }
        }

        return $paymentMethod;
    }

    public function transformTranslations(array $translations): array
    {
        $result = [];

        foreach($translations as $locale => $data) {
            $translation = [
                'name' => $data['name']
            ];

            if(!empty($data['description'])) {
                $translation['description'] = $data['description'];
            }

            if(!empty($data['customFields'])) {
                $translation['customFields'] = $data['customFields'];
            }

            $result[$locale] = $translation;
        }

        return $result;
    }
}