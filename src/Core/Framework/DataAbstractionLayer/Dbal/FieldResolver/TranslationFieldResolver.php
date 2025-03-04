<?php

namespace Dustin\ShopwareUtils\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver;

use Doctrine\DBAL\Connection;
use Dustin\ShopwareUtils\Core\Framework\DataAbstractionLayer\DefinitionUtil;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\FieldResolverContext;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\Uuid\Uuid;

/*
 * This class overwrites Shopware's TranslationFieldResolver and improves behavior.
 * The base class does not take care of the fields from the translation definition.
 */

class TranslationFieldResolver extends \Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver
{
    public function __construct(protected readonly Connection $_connection)
    {
        parent::__construct($_connection);
    }

    public function join(FieldResolverContext $context): string
    {
        $field = $context->getField();

        if(!$field instanceof TranslatedField) {
            return $context->getAlias();
        }

        $definition = $context->getDefinition();
        $translationDefinition = $definition->getTranslationDefinition();

        if(!$translationDefinition) {
            throw new \RuntimeException(\sprintf('Can not detect translation definition of entity %s', $definition->getEntityName()));
        }

        $inherited = $definition->isInheritanceAware() && $context->getContext()->considerInheritance();
        $alias = $context->getAlias().'.'.$translationDefinition->getEntityName();
        $fieldAlias = $alias.'.'.$field->getPropertyName();

        if($context->getQuery()->hasState($fieldAlias)) {
            return $alias;
        }

        $context->getQuery()->addState($fieldAlias);

        if($context->getQuery()->hasState($alias)) {
            $this->_addTranslationSelects(
                $context->getQuery(),
                $translationDefinition,
                $field,
                $context->getContext(),
                $context->getPath(),
                $alias,
            );

            if($inherited) {
                $this->_addTranslationSelects(
                    $context->getQuery(),
                    $translationDefinition,
                    $field,
                    $context->getContext(),
                    $context->getPath().'.parent',
                    $context->getAlias().'.parent.'.$translationDefinition->getEntityName(),
                );
            }

            return $alias;
        }
        $context->getQuery()->addState($alias);

        $this->_addTranslationJoin(
            $context->getQuery(),
            $definition,
            $translationDefinition,
            $field,
            $context->getContext(),
            $context->getPath(),
            $context->getAlias(),
        );

        if($inherited) {
            $this->_addTranslationJoin(
                $context->getQuery(),
                $definition,
                $translationDefinition,
                $field,
                $context->getContext(),
                $context->getPath().'.parent',
                $context->getAlias().'.parent',
            );
        }

        return $alias;
    }

    protected function _addTranslationSelects(
        QueryBuilder $mainQuery,
        EntityDefinition $translationDefinition,
        TranslatedField $field,
        Context $context,
        string $path,
        string $alias,
    ): void {
        $translationQuery = $mainQuery->getTranslationQueryBuilder(EntityDefinitionQueryHelper::escape($alias));
        $translationChain = \array_reverse(EntityDefinitionQueryHelper::buildTranslationChain(
            $path,
            $context,
            false,
        ));
        foreach($translationChain as $translationTableAlias) {
            $translationQuery?->addSelect($this->_getSelectSQL($translationDefinition, $field, $translationTableAlias));
        }
    }

    protected function _addTranslationJoin(
        QueryBuilder $query,
        EntityDefinition $definition,
        EntityDefinition $translationDefinition,
        TranslatedField $field,
        Context $context,
        string $path,
        string $alias,
    ): void {
        $rootVersionFieldName = null;
        $translatedVersionFieldName = null;

        if($definition->isVersionAware()) {
            $rootVersionFieldName = DefinitionUtil::getPrimaryVersionField($definition)?->getStorageName();
            $translatedVersionFieldName = DefinitionUtil::getPrimaryVersionField($translationDefinition)?->getStorageName();
        }

        $translationQuery = $this->_getTranslationQuery(
            $translationDefinition,
            $this->_getSelectSQL($translationDefinition, $field, '#alias#'),
            $path,
            $context,
            $translatedVersionFieldName
        );

        foreach($translationQuery->getParameters() as $key => $value) {
            $query->setParameter($key, $value);
        }

        $translationPrimaryKey = DefinitionUtil::getPrimaryKey($translationDefinition, true);

        if($translationPrimaryKey === null) {
            throw new \RuntimeException(\sprintf('Primary key for entity %s was not found.', $translationDefinition->getEntityName()));
        }

        $translationAlias = $alias.'.'.$translationDefinition->getEntityName();
        $versionJoin = '';
        $variables = [
            '#alias#' => EntityDefinitionQueryHelper::escape($translationAlias),
            '#foreignKey#' => EntityDefinitionQueryHelper::escape($translationPrimaryKey->getStorageName()),
            '#on#' => EntityDefinitionQueryHelper::escape($alias),
        ];

        if($rootVersionFieldName && $translatedVersionFieldName) {
            $variables['#rootVersionField#'] = EntityDefinitionQueryHelper::escape($rootVersionFieldName);
            $variables['#translatedVersionField#'] = EntityDefinitionQueryHelper::escape($translatedVersionFieldName);
            $versionJoin = ' AND #alias#.#translatedVersionField# = #on#.#rootVersionField#';
        }

        $primaryKey = DefinitionUtil::getPrimaryKey($definition, true);

        if($primaryKey === null) {
            throw new \RuntimeException(\sprintf('Primary key for entity %s was not found.', $definition->getEntityName()));
        }

        $query->addTranslationJoin(
            fromAlias: EntityDefinitionQueryHelper::escape($alias),
            joinAlias: EntityDefinitionQueryHelper::escape($translationAlias),
            queryBuilder: $translationQuery,
            joinCondition: \str_replace(
                \array_keys($variables),
                \array_values($variables),
                '#alias#.#foreignKey# = #on#.'.EntityDefinitionQueryHelper::escape($primaryKey->getStorageName()).$versionJoin,
            ),
        );
    }

    protected function _getSelectSQL(
        EntityDefinition $translationDefinition,
        TranslatedField $field,
        string $alias
    ): string {
        $translationField = $translationDefinition->getFields()->get($field->getPropertyName());

        if(!$translationField instanceof StorageAware) {
            throw DataAbstractionLayerException::propertyNotFound(
                $field->getPropertyName(),
                $translationDefinition->getEntityName(),
            );
        }

        return EntityDefinitionQueryHelper::escape($alias).'.'.EntityDefinitionQueryHelper::escape($translationField->getStorageName()).' as '.EntityDefinitionQueryHelper::escape($alias.'.'.$translationField->getPropertyName());
    }

    private function _getTranslationQuery(
        EntityDefinition $translationDefinition,
        string $select,
        string $on,
        Context $context,
        ?string $versionFieldName = null,
    ): QueryBuilder {
        $table = $translationDefinition->getEntityName();
        $query = new QueryBuilder($this->_connection);

        $chain = \array_reverse($context->getLanguageIdChain());
        $first = \array_shift($chain);
        $firstAlias = $on.'.translation';
        $primaryKeyField = DefinitionUtil::getPrimaryKey($translationDefinition, true);
        $foreignKey = EntityDefinitionQueryHelper::escape($firstAlias).'.'.EntityDefinitionQueryHelper::escape($primaryKeyField->getStorageName());

        $query->addSelect($foreignKey);

        if($versionFieldName !== null) {
            $versionKey = EntityDefinitionQueryHelper::escape($firstAlias).'.'.EntityDefinitionQueryHelper::escape($versionFieldName);
            $query->addSelect($versionKey);
        }

        $query->addSelect(\str_replace('#alias#', $firstAlias, $select));
        $query->from(EntityDefinitionQueryHelper::escape($table), EntityDefinitionQueryHelper::escape($firstAlias));
        $query->where(EntityDefinitionQueryHelper::escape($firstAlias).'.`language_id` = :languageId');
        $query->setParameter('languageId', Uuid::fromHexToBytes($first));

        foreach($chain as $i => $language) {
            ++$i;

            $condition = '#firstAlias#.#column# = #alias#.#column# AND #alias#.`language_id` = :languageId'.$i;
            $alias = $on.'.translation.override_'.$i;
            $variables = [
                '#column#' => EntityDefinitionQueryHelper::escape($primaryKeyField->getStorageName()),
                '#alias#' => EntityDefinitionQueryHelper::escape($alias),
                '#firstAlias#' => EntityDefinitionQueryHelper::escape($firstAlias),
            ];

            if($versionFieldName !== null) {
                $variables['#versionFieldName#'] = EntityDefinitionQueryHelper::escape($versionFieldName);
                $condition .= ' AND #firstAlias#.#versionFieldName# = #alias#.#versionFieldName#';
            }

            $query->leftJoin(
                EntityDefinitionQueryHelper::escape($firstAlias),
                EntityDefinitionQueryHelper::escape($table),
                EntityDefinitionQueryHelper::escape($alias),
                \str_replace(\array_keys($variables), \array_values($variables), $condition)
            );

            $query->addSelect(\str_replace('#alias#', $alias, $select));
            $query->setParameter('languageId'.$i, Uuid::fromHexToBytes($language));
        }

        return $query;
    }
}