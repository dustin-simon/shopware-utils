<?php

namespace Dustin\ShopwareUtils\Core\System\CustomField\Installer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\JsonFileInstaller;
use Dustin\ShopwareUtils\Core\Framework\Installer\TransformInterface;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin as ShopwarePlugin;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class CustomFieldInstaller extends JsonFileInstaller
{
    private static ?self $instance = null;

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $relationRepository,
        private readonly EntityRepository $customFieldRepository,
        Connection $connection,
        ValidatorInterface $validator,
        ValidationConstraintFactory $constraintFactory = new CustomFieldValidationConstraintFactory(),
        TransformInterface $transformer = new CustomFieldTransformer()
    ) {
        parent::__construct(
            $connection,
            $validator,
            $constraintFactory,
            $transformer
        );
    }

    public static function getInstance(ContainerInterface $container): self
    {
        if(self::$instance === null) {
            self::$instance = new self(
                $container->get('custom_field_set.repository'),
                $container->get('custom_field_set_relation.repository'),
                $container->get('custom_field.repository'),
                $container->get(Connection::class),
                (new ValidatorBuilder())->getValidator()
            );
        }

        return self::$instance;
    }

    protected function sync(array $data, AdditionalBundle|Plugin $bundle, ShopwarePlugin $plugin, Context $context): void
    {
        $setNames = \array_keys($data);
        $existingSets = $this->getCustomFields($setNames);
        $relationsToDelete = [];
        $customFieldsToDelete = [];

        foreach($data as &$set) {
            $setName = $set['name'];
            $existingSet = $existingSets[$setName] ?? null;

            if($existingSet === null) {
                $set['id'] = Uuid::randomHex();

                continue;
            }

            $set['id'] = $existingSet['id'];
            $customFields = [];

            foreach($set['customFields'] as &$customField) {
                $customFieldName = $customField['name'];
                $customFields[$customFieldName] = $customFieldName;
                $existingCustomField = $existingSet['customFields'][$customFieldName] ?? null;

                $customField['id'] = $existingCustomField !== null ? $existingCustomField['id'] : Uuid::randomHex();
            }

            $entities = [];

            foreach($set['relations'] as &$relation) {
                $entity = $relation['entityName'];
                $entities[$entity] = $entity;
                $existingRelation = $existingSet['relations'][$entity] ?? null;

                $relation['id'] = $existingRelation !== null ? $existingRelation['id'] : Uuid::randomHex();
            }

            foreach($existingSet['relations'] as $entity => $existingRelation) {
                if(!isset($entities[$entity])) {
                    $relationsToDelete[] = ['id' => $existingRelation['id']];
                }
            }

            foreach($existingSet['customFields'] as $name => $existingCustomField) {
                if(!isset($customFields[$name])) {
                    $customFieldsToDelete[] = $existingCustomField;
                }
            }
        }

        $this->customFieldSetRepository->upsert(\array_values($data), $context);

        if(!empty($relationsToDelete)) {
            $this->relationRepository->delete(\array_values($relationsToDelete), $context);
        }

        if(!empty($customFieldsToDelete)) {
            $this->customFieldRepository->delete(\array_values($customFieldsToDelete), $context);
        }
    }

    protected function deleteByIdentifiers(array $identifiers, Context $context): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`)) FROM `custom_field_set` WHERE `name` IN (:setNames)',
            ['setNames' => $identifiers],
            ['setNames' => ArrayParameterType::STRING]
        );

        $data = \array_map(
            function (string $id): array {
                return ['id' => $id];
            },
            $ids
        );

        $this->customFieldSetRepository->delete($data, $context);
    }

    protected function getBundleDataDir(AdditionalBundle|Plugin $bundle): string
    {
        return $bundle->getCustomFieldsPath();
    }

    protected function getObsoleteIdentifiers(Plugin|AdditionalBundle $bundle): array
    {
        return $bundle->getObsoleteCustomFieldSets();
    }

    private function getCustomFields(array $setNames): array
    {
        $selectCustomFields = <<<'SQL'
            SELECT
                `custom_field`.`id` as `customFieldId`,
                `custom_field`.`name` as `customFieldName`,
                `custom_field_set`.`id` as `setId`,
                `custom_field_set`.`name` as `setName`
            FROM `custom_field`
            LEFT JOIN `custom_field_set` ON `custom_field`.`set_id` = `custom_field_set`.`id`
            WHERE `custom_field_set`.`name` IN (:setNames)
        SQL;

        $selectRelations = <<<'SQL'
            SELECT
                `custom_field_set_relation`.`id` as `id`,
                `set_id` as `setId`,
                `custom_field_set`.`name` as `setName`,
                `entity_name` as `entityName`
            FROM `custom_field_set_relation`
            LEFT JOIN `custom_field_set` ON `custom_field_set_relation`.`set_id` = `custom_field_set`.`id`
            WHERE `custom_field_set`.`name` IN (:setNames)
        SQL;

        $customFields = $this->connection->fetchAllAssociative($selectCustomFields, ['setNames' => $setNames], ['setNames' => ArrayParameterType::STRING]);
        $relations = $this->connection->fetchAllAssociative($selectRelations, ['setNames' => $setNames], ['setNames' => ArrayParameterType::STRING]);
        $sets = [];

        foreach($customFields as $customField) {
            $setName = $customField['setName'];

            if(!isset($sets[$setName])) {
                $sets[$setName] = [
                    'id' => Uuid::fromBytesToHex($customField['setId']),
                    'customFields' => [],
                    'relations' => [],
                ];
            }

            $customFieldName = $customField['customFieldName'];

            $sets[$setName]['customFields'][$customFieldName] = [
                'id' => Uuid::fromBytesToHex($customField['customFieldId']),
                'name' => $customFieldName,
            ];
        }

        foreach($relations as $relation) {
            $setName = $relation['setName'];
            $entity = $relation['entityName'];

            $sets[$setName]['relations'][$entity] = [
                'id' => Uuid::fromBytesToHex($relation['id']),
            ];
        }

        return $sets;
    }
}