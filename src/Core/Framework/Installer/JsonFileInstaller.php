<?php

namespace Dustin\ShopwareUtils\Core\Framework\Installer;

use Doctrine\DBAL\Connection;
use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\Exception\FileValidationFailedException;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Dustin\ShopwareUtils\Core\Framework\Util\Util;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin as ShopwarePlugin;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class JsonFileInstaller
{
    private static ?array $languageIds = null;

    public function __construct(
        protected readonly Connection $connection,
        protected readonly ValidatorInterface $validator,
        protected readonly ValidationConstraintFactory $constraintFactory,
        protected readonly TransformInterface $transformer
    ) {}

    abstract protected function sync(array $data, AdditionalBundle|Plugin $bundle, ShopwarePlugin $plugin, Context $context): void;

    abstract protected function deleteByIdentifiers(array $identifiers, Context $context): void;

    abstract protected function getBundleDataDir(AdditionalBundle|Plugin $bundle): string;

    abstract protected function getObsoleteIdentifiers(AdditionalBundle|Plugin $bundle): array;

    public function update(AdditionalBundle|Plugin $bundle, ShopwarePlugin $plugin, Context $context): void
    {
        $data = Util::iteratorToArray($this->getUpdateData($bundle));

        if(empty($data)) {
            $this->deleteObsolete($bundle, $context);

            return;
        }

        $this->sync($data, $bundle, $plugin, $context);
        $this->deleteObsolete($bundle, $context);
    }

    public function delete(AdditionalBundle|Plugin $bundle, Context $context): void
    {
        $updateData = Util::iteratorToArray($this->getUpdateData($bundle));
        $identifiers = \array_merge(
            \array_keys($updateData),
            $this->getObsoleteIdentifiers($bundle)
        );

        if(!empty($identifiers)) {
            $this->deleteByIdentifiers($identifiers, $context);
        }
    }

    protected function deleteObsolete(AdditionalBundle|Plugin $bundle, Context $context): void
    {
        $identifiers = $this->getObsoleteIdentifiers($bundle);

        if(!empty($identifiers)) {
            $this->deleteByIdentifiers($identifiers, $context);
        }
    }

    protected function getUpdateData(AdditionalBundle|Plugin $bundle): \Generator
    {
        $dir = $this->getBundleDataDir($bundle);

        if(!\is_dir($dir)) {
            return;
        }

        $files = \glob(\rtrim($dir, '/').'/*.json');

        foreach($files as $file) {
            if(!$this->isValidJsonFile($file)) {
                continue;
            }

            $identifier = $this->fileNameToIdentifier($file);
            $data = $this->decode($file);
            $this->validate($data, $bundle, $file, $identifier);

            yield $identifier => $this->transformer->transform($identifier, $data, $bundle);
        }
    }

    protected function isValidJsonFile(string $file): bool
    {
        $file = \pathinfo($file, \PATHINFO_BASENAME);
        $pattern = '/[a-z0-9_]+.json/';
        $result = [];

        \preg_match($pattern, $file, $result);

        return ($result[0] ?? null) === $file;
    }

    protected function decode(string $file): array
    {
        return \json_decode(
            \file_get_contents($file),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );
    }

    protected function validate(array $data, AdditionalBundle|Plugin $bundle, string $file, string $identifier): void
    {
        $constraints = $this->constraintFactory->createConstraints($bundle, $identifier);
        $violations = $this->validator->validate($data, $constraints);

        if(\count($violations) > 0) {
            throw new FileValidationFailedException($file, $violations, $data);
        }
    }

    protected function fileNameToIdentifier(string $file): string
    {
        return \pathinfo($file, \PATHINFO_FILENAME);
    }

    protected function fetchIdMap(string $sql, array $params = [], array $paramMeta = []): array
    {
        $result = $this->connection->fetchAllKeyValue($sql, $params, $paramMeta);

        return \array_map(
            function (string $id): string {
                if(!Uuid::isValid($id)) {
                    return Uuid::fromBytesToHex($id);
                }

                return $id;
            },
            $result
        );
    }

    protected function resolveIds(array &$data, string $sql, array $params = [], array $paramMeta = []): void
    {
        $idMap = $this->fetchIdMap($sql, $params, $paramMeta);

        foreach($data as $identifier => &$row) {
            $row['id'] = $idMap[$identifier] ?? Uuid::randomHex();
        }
    }

    protected function resolveTranslationsLanguageIds(array &$data): void
    {
        foreach($data as &$row) {
            if(!\is_array($row['translations'] ?? null)) {
                continue;
            }

            $translations = &$row['translations'];

            foreach($translations as $locale => &$translation) {
                $languageId = $this->getLanguageId($locale);

                if($languageId === null) {
                    unset($translations[$locale]);

                    continue;
                }

                $translation['languageId'] = $languageId;
            }
        }
    }

    protected function getLanguageId(string $locale): ?string
    {
        return $this->getLanguageLocaleMap()[$locale] ?? null;
    }

    private function getLanguageLocaleMap(): array
    {
        if(static::$languageIds === null) {
            $sql = '
                SELECT 
                    `locale`.`code` as `locale`,
                    `language`.`id` as `id`
                FROM `language`
                LEFT JOIN `locale` ON `language`.`locale_id` = `locale`.`id`
            ';

            static::$languageIds = $this->fetchIdMap($sql);
        }

        return static::$languageIds;
    }
}