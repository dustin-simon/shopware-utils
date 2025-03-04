<?php

namespace Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\Exception\InstallException;
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

class MailTemplateInstaller extends JsonFileInstaller
{
    private static ?self $instance = null;

    public function __construct(
        private readonly EntityRepository $mailTemplateRepository,
        Connection $connection,
        ValidatorInterface $validator,
        ValidationConstraintFactory $constraintFactory = new MailTemplateValidationConstraintFactory(),
        TransformInterface $transformer = new MailTemplateTransformer(),
    ) {
        parent::__construct($connection, $validator, $constraintFactory, $transformer);
    }

    public static function getInstance(ContainerInterface $container): self
    {
        if(self::$instance === null) {
            self::$instance = new self(
                $container->get('mail_template.repository'),
                $container->get(Connection::class),
                (new ValidatorBuilder())->getValidator(),
            );
        }

        return self::$instance;
    }

    protected function sync(array $data, AdditionalBundle|Plugin $bundle, ShopwarePlugin $plugin, Context $context): void
    {
        $ids = array_column($data, 'id');
        $query = 'SELECT LOWER(HEX(`id`)) FROM `mail_template` WHERE `id` IN (:ids)';
        $ids = $this->connection->fetchFirstColumn(
            $query,
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::STRING]
        );

        $data = \array_filter(
            $data,
            function (array $mailTemplate) use ($ids): bool {
                return !\in_array($mailTemplate['id'], $ids, true);
            }
        );

        if(empty($data)) {
            return;
        }

        $typeIds = $this->fetchIdMap(
            'SELECT `technical_name`, `id` FROM `mail_template_type` WHERE `technical_name` IN (:technicalNames)',
            ['technicalNames' => \array_unique(\array_column($data, 'type'))],
            ['technicalNames' => ArrayParameterType::STRING]
        );

        foreach($data as $identifier => &$mailTemplate) {
            $typeName = $mailTemplate['type'];

            if(!isset($typeIds[$typeName])) {
                throw new InstallException(\sprintf('Mail template type \'%s\' for mail template \'%s\' (%s) was not found.', $typeName, $identifier, $bundle->getName()));
            }

            $mailTemplate['mailTemplateTypeId'] = $typeIds[$typeName];
            unset($mailTemplate['type']);
        }

        $this->resolveTranslationsLanguageIds($data);

        $this->mailTemplateRepository->upsert(\array_values($data), $context);
    }

    protected function deleteByIdentifiers(array $identifiers, Context $context): void
    {
        // mail templates will not be deleted on uninstallation
    }

    protected function getBundleDataDir(Plugin|AdditionalBundle $bundle): string
    {
        return $bundle->getMailTemplatesPath();
    }

    protected function getObsoleteIdentifiers(Plugin|AdditionalBundle $bundle): array
    {
        return [];
    }

}