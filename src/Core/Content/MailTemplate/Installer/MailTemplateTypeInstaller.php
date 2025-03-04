<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class MailTemplateTypeInstaller extends JsonFileInstaller
{
    private static ?self $instance = null;

    public function __construct(
        private readonly EntityRepository $mailTemplateTypeRepository,
        Connection $connection,
        ValidatorInterface $validator,
        ValidationConstraintFactory $constraintFactory = new MailTemplateTypeValidationConstraintFactory(),
        TransformInterface $transformer = new MailTemplateTypeTransformer()
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
                $container->get('mail_template_type.repository'),
                $container->get(Connection::class),
                (new ValidatorBuilder())->getValidator()
            );
        }

        return self::$instance;
    }

    protected function sync(array $data, AdditionalBundle|Plugin $bundle, ShopwarePlugin $plugin, Context $context): void
    {
        $technicalNames = \array_keys($data);
        $selectTypes = <<<'SQL'
            SELECT 
                `technical_name` as `technicalName`,
                `id` as `id`
            FROM `mail_template_type`
            WHERE `technical_name` IN (:technicalNames)
        SQL;

        $this->resolveIds($data, $selectTypes, ['technicalNames' => $technicalNames], ['technicalNames' => ArrayParameterType::STRING]);
        $this->resolveTranslationsLanguageIds($data);

        $this->mailTemplateTypeRepository->upsert(\array_values($data), $context);
    }

    protected function deleteByIdentifiers(array $identifiers, Context $context): void
    {
        if(empty($identifiers)) {
            return;
        }

        $ids = $this->connection->fetchFirstColumn(
            '
                SELECT 
                    LOWER(HEX(`mail_template_type`.`id`))
                FROM `mail_template_type`
                LEFT JOIN `mail_template` ON `mail_template_type`.`id` = `mail_template`.`mail_template_type_id`
                WHERE `mail_template`.`id` IS NULL
                AND `mail_template_type`.`technical_name` IN (:technicalNames)
            ',
            ['technicalNames' => $identifiers],
            ['technicalNames' => ArrayParameterType::STRING]
        );

        if(empty($ids)) {
            return;
        }

        $ids = \array_map(
            function (string $id): array {
                return ['id' => $id];
            },
            $ids
        );

        $this->mailTemplateTypeRepository->delete($ids, $context);
    }

    protected function getBundleDataDir(Plugin|AdditionalBundle $bundle): string
    {
        return $bundle->getMailTemplateTypesPath();
    }

    protected function getObsoleteIdentifiers(Plugin|AdditionalBundle $bundle): array
    {
        return $bundle->getObsoleteMailTemplateTypes();
    }
}