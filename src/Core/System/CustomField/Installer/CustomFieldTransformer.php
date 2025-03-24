<?php

namespace Dustin\ShopwareUtils\Core\System\CustomField\Installer;

use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\TransformInterface;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Dustin\ShopwareUtils\Core\System\CustomField\CustomFieldTypes;
use Shopware\Core\System\CustomField\CustomFieldTypes as ShopwareCustomFieldTypes;

class CustomFieldTransformer implements TransformInterface
{
    public function transform(string $identifier, array $data, AdditionalBundle|Plugin $bundle): array
    {
        return [
            'name' => $data['name'],
            'config' => $data['config'],
            'global' => $data['global'] ?? false,
            'position' => $data['position'] ?? 1,
            'customFields' => array_map(
                function (array $customField): array {
                    return $this->transformCustomField($customField);
                },
                $data['customFields']
            ),
            'relations' => array_map(
                function (string $relation): array {
                    return $this->transformRelation($relation);
                },
                $data['relations']
            ),
        ];
    }

    public function transformCustomField(array $customField): array
    {
        return [
            'name' => $customField['name'],
            'type' => $this->getCustomFieldType($customField['type']),
            'config' => $this->getCustomFieldConfig($customField),
            'allowCustomerWrite' => $customField['allowCustomerWrite'] ?? false,
            'allowCartExpose' => $customField['allowCartExpose'] ?? false,
        ];
    }

    public function transformRelation(string $relation): array
    {
        return ['entityName' => $relation];
    }

    protected function emptyTranslations(): array
    {
        return [
            'en-GB' => null,
            'de-DE' => null,
        ];
    }

    protected function getCustomFieldConfig(array $customField): array
    {
        $origin = $customField['config'];

        $config = [
            'label' => $customField['config']['label'],
            'customFieldPosition' => $origin['position'] ?? 1,
            'helpText' => $origin['helpText'] ?? $this->emptyTranslations(),
            'placeholder' => $origin['placeholder'] ?? $this->emptyTranslations(),
        ];

        if(($origin['required'] ?? false) === true) {
            $config['validation'] = 'required';
        }

        switch($customField['type']) {
            case CustomFieldTypes::BOOL:
                $boolType = $origin['component'] ?? ShopwareCustomFieldTypes::CHECKBOX;
                $config = array_merge(
                    $config,
                    [
                        'type' => $boolType,
                        'componentName' => 'sw-field',
                        'customFieldType' => $boolType,
                    ]
                );

                break;

            case CustomFieldTypes::COLORPICKER:
                $config = array_merge(
                    $config,
                    [
                        'type' => ShopwareCustomFieldTypes::COLORPICKER,
                        'componentName' => 'sw-field',
                        'customFieldType' => ShopwareCustomFieldTypes::COLORPICKER,
                    ]
                );

                break;

            case CustomFieldTypes::DATETIME:
                $config = array_merge(
                    $config,
                    [
                        'type' => ShopwareCustomFieldTypes::DATE,
                        'componentName' => 'sw-field',
                        'customFieldType' => ShopwareCustomFieldTypes::DATE,
                        'config' => [
                            'time_24hr' => true,
                        ],
                        'dateType' => ShopwareCustomFieldTypes::DATETIME,
                    ]
                );

                break;

            case CustomFieldTypes::ENTITY:
                $config = array_merge(
                    $config,
                    [
                        'componentName' => $origin['multiselect'] === true ? 'sw-entity-multi-id-select' : 'sw-entity-single-select',
                        'customFieldType' => ShopwareCustomFieldTypes::ENTITY,
                        'entity' => $origin['entity'],
                    ]
                );

                break;

            case CustomFieldTypes::FLOAT:
                $config = array_merge(
                    $config,
                    [
                        'type' => ShopwareCustomFieldTypes::NUMBER,
                        'componentName' => 'sw-field',
                        'customFieldType' => ShopwareCustomFieldTypes::NUMBER,
                        'numberType' => ShopwareCustomFieldTypes::FLOAT,
                        'max' => $origin['max'] ?? null,
                        'min' => $origin['min'] ?? null,
                        'step' => $origin['step'] ?? null,
                        'allowEmpty' => $origin['allowEmpty'] ?? false,
                        'digits' => $origin['digits'] ?? 4,
                    ]
                );

                break;

            case CustomFieldTypes::INT:
                $config = array_merge(
                    $config,
                    [
                        'type' => ShopwareCustomFieldTypes::NUMBER,
                        'componentName' => 'sw-field',
                        'customFieldType' => ShopwareCustomFieldTypes::NUMBER,
                        'numberType' => ShopwareCustomFieldTypes::INT,
                        'max' => $origin['max'] ?? null,
                        'min' => $origin['min'] ?? null,
                        'step' => $origin['step'] ?? null,
                        'allowEmpty' => $origin['allowEmpty'] ?? false,
                    ]
                );

                break;

            case CustomFieldTypes::JSON:
                $config = array_merge(
                    $config,
                    [
                        'componentName' => 'sw-textarea-field',
                        'customFieldType' => ShopwareCustomFieldTypes::TEXT,
                    ]
                );

                break;

            case CustomFieldTypes::PRICE:
                $config = array_merge(
                    $config,
                    [
                        'customFieldType' => ShopwareCustomFieldTypes::PRICE,
                    ]
                );

                break;

            case CustomFieldTypes::HTML:
                $config = array_merge(
                    $config,
                    [
                        'componentName' => 'sw-text-editor',
                        'customFieldType' => 'textEditor',
                    ]
                );

                break;

            case CustomFieldTypes::MEDIA:
                $config = array_merge(
                    $config,
                    [
                        'componentName' => 'sw-media-field',
                        'customFieldType' => ShopwareCustomFieldTypes::MEDIA,
                    ]
                );

                break;

            case CustomFieldTypes::SELECT:
                $config = array_merge(
                    $config,
                    [
                        'componentName' => $origin['multiselect'] === true ? 'sw-multi-select' : 'sw-single-select',
                        'customFieldType' => ShopwareCustomFieldTypes::SELECT,
                        'options' => $origin['options'],
                    ]
                );

                break;

            case CustomFieldTypes::TEXT:
                $config = array_merge(
                    $config,
                    [
                        'type' => ShopwareCustomFieldTypes::TEXT,
                        'componentName' => (bool) ($origin['large'] ?? false) === true ? 'sw-textarea-field' : 'sw-field',
                        'customFieldType' => ShopwareCustomFieldTypes::TEXT,
                    ]
                );

                break;
        }

        return $config;
    }

    protected function getCustomFieldType(string $type): string
    {
        return match ($type) {
            CustomFieldTypes::COLORPICKER, CustomFieldTypes::MEDIA => ShopwareCustomFieldTypes::TEXT,
            CustomFieldTypes::ENTITY => ShopwareCustomFieldTypes::SELECT,
            default => $type
        };
    }
}
