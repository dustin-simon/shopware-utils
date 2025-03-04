<?php

namespace Dustin\ShopwareUtils\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;

class DefinitionUtil
{

    public static function getPrimaryVersionField(EntityDefinition $definition): VersionField|ReferenceVersionField|null
    {
        return $definition->getPrimaryKeys()->filter(
            function (Field $field): bool {
                return static::isVersionField($field);
            }
        )->first();
    }

    public static function getPrimaryKey(EntityDefinition $definition, bool $excludeVersionField = false): (Field&StorageAware)|null
    {
        $keys = $definition->getPrimaryKeys();

        if($excludeVersionField === true) {
            $keys = $keys->filter(
                function (Field $field): bool {
                    return !static::isVersionField($field);
                }
            );
        }

        return $keys->first();
    }

    public static function isVersionField(Field $field): bool
    {
        return $field instanceof VersionField || $field instanceof ReferenceVersionField;
    }
}