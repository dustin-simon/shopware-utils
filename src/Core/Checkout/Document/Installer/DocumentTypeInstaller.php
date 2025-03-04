<?php

namespace Dustin\ShopwareUtils\Core\Checkout\Document\Installer;

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

class DocumentTypeInstaller extends JsonFileInstaller
{
    private static ?self $instance = null;

    public function __construct(
        protected readonly EntityRepository $documentTypeRepository,
        protected readonly EntityRepository $documentConfigRepository,
        Connection $connection,
        ValidatorInterface $validator,
        ValidationConstraintFactory $constraintFactory = new DocumentTypeValidationConstraintFactory(),
        TransformInterface $transformer = new DocumentTypeTransformer()
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
                $container->get('document_type.repository'),
                $container->get('document_base_config.repository'),
                $container->get(Connection::class),
                (new ValidatorBuilder())->getValidator(),
            );
        }

        return self::$instance;
    }

    protected function sync(array $data, Plugin|AdditionalBundle $bundle, ShopwarePlugin $plugin, Context $context): void
    {
        $this->updateDocumentTypes($data, $context);

        $updateData = [];

        foreach($data as $documentType) {
            $configId = $this->getDocumentConfigId($documentType['id']);

            if($configId !== null) {
                continue;
            }

            $updateData[] = [
                'id' => Uuid::randomHex(),
                'documentTypeId' => $documentType['id'],
                'name' => $documentType['technicalName'],
                'fileNamePrefix' => $documentType['fileNamePrefix'] ?? null,
                'fileNameSuffix' => $documentType['fileNameSuffix'] ?? null,
                'global' => true,
                'config' => $documentType['config'] ?? null
            ];
        }

        if(!empty($updateData)) {
            $this->documentConfigRepository->upsert(\array_values($updateData), $context);
        }
    }

    protected function deleteByIdentifiers(array $identifiers, Context $context): void
    {
        $ids = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(`id`)) FROM `document_type` WHERE `technical_name` IN (:technicalNames)',
            ['technicalNames' => $identifiers],
            ['technicalNames' => ArrayParameterType::STRING]
        );

        $inUse = $this->getDocumentTypesInUse();

        foreach($ids as $id) {
            if(\in_array($id, $inUse)) {
                continue;
            }

            try {
                $this->documentTypeRepository->delete([['id' => $id]], $context);
            } catch(\Throwable) {
                continue;
            }
        }
    }

    protected function getBundleDataDir(Plugin|AdditionalBundle $bundle): string
    {
        return $bundle->getDocumentTypesPath();
    }

    protected function getObsoleteIdentifiers(Plugin|AdditionalBundle $bundle): array
    {
        return $bundle->getObsoleteDocumentTypes();
    }

    protected function updateDocumentTypes(array &$data, Context $context): void
    {
        $ids = $this->fetchIdMap(
            'SELECT `technical_name`, LOWER(HEX(`id`)) FROM `document_type` WHERE `technical_name` IN (:technicalNames)',
            ['technicalNames' => \array_keys($data)],
            ['technicalNames' => ArrayParameterType::STRING]
        );

        $updateData = [];

        foreach($data as &$documentType) {
            $documentType['id'] = $ids[$documentType['technicalName']] ?? Uuid::randomHex();
            $updateData[] = [
                'id' => $documentType['id'],
                'technicalName' => $documentType['technicalName'],
                'translations' => $documentType['translations'],
            ];
        }

        $this->resolveTranslationsLanguageIds($updateData);

        $this->documentTypeRepository->upsert(\array_values($updateData), $context);
    }

    protected function getDocumentTypesInUse(): array
    {
        return $this->connection->fetchFirstColumn('SELECT DISTINCT LOWER(HEX(`document_type_id`)) FROM `document`');
    }

    protected function getDocumentConfigId(string $documentTypeId): ?string
    {
        return $this->connection->fetchOne(
            '
                SELECT 
                    LOWER(HEX(`id`)) 
                FROM `document_base_config`
                WHERE `document_type_id` = :documentTypeId
                AND `global` = 1
                LIMIT 1
            ',
            ['documentTypeId' => Uuid::fromHexToBytes($documentTypeId)]
        ) ?: null;
    }
}