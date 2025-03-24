<?php

namespace Dustin\ShopwareUtils\Core\Checkout\Payment\Installer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Dustin\ShopwareUtils\Core\Framework\AdditionalBundle;
use Dustin\ShopwareUtils\Core\Framework\Installer\JsonFileInstaller;
use Dustin\ShopwareUtils\Core\Framework\Installer\MediaInstaller;
use Dustin\ShopwareUtils\Core\Framework\Installer\TransformInterface;
use Dustin\ShopwareUtils\Core\Framework\Installer\ValidationConstraintFactory;
use Dustin\ShopwareUtils\Core\Framework\Plugin;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin as ShopwarePlugin;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ValidatorBuilder;

class PaymentMethodInstaller extends JsonFileInstaller
{

    private static ?self $instance = null;

    private string|null|false $mediaFolderId = null;

    public function __construct(
        protected readonly EntityRepository $paymentMethodRepository,
        protected readonly MediaInstaller $mediaInstaller,
        Connection $connection,
        ValidatorInterface $validator,
        ?ValidationConstraintFactory $constraintFactory = new PaymentMethodValidationConstraintFactory(),
        TransformInterface $transformer = new PaymentMethodTransformer(),
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
                $container->get('payment_method.repository'),
                new MediaInstaller(
                    $container->get('media.repository'),
                    $container->get('media_folder.repository'),
                    $container->get(FileSaver::class)
                ),
                $container->get(Connection::class),
                (new ValidatorBuilder())->getValidator(),
            );
        }

        return self::$instance;
    }

    protected function sync(array $data, Plugin|AdditionalBundle $bundle, ShopwarePlugin $plugin, Context $context): void
    {
        $technicalNames = \array_keys($data);

        $ids = $this->fetchIdMap(
            'SELECT `technical_name`, LOWER(HEX(`id`)) FROM `payment_method` WHERE `technical_name` IN (:technicalNames)',
            ['technicalNames' => $technicalNames],
            ['technicalNames' => ArrayParameterType::STRING]
        );

        $pluginId = $this->getPluginId($plugin);

        foreach($data as $technicalName => &$paymentMethod) {
            $id = $ids[$technicalName] ?? null;

            if($id !== null) {
                $this->removeCreateOnlyFields($paymentMethod);
            } else {
                $id = Uuid::randomHex();
            }

            $paymentMethod['id'] = $id;

            if(($mediaFile = ($paymentMethod['media'] ?? null)) !== null) {
                $folderId = $this->getMediaFolderId($context);
                $file = $bundle->getPaymentMethodsPath().'/'.$mediaFile;

                $mediaId = $this->mediaInstaller->installMediaFile($file, $folderId, $context);
                $paymentMethod['mediaId'] = $mediaId;
            }

            unset($paymentMethod['media']);
            $paymentMethod['pluginId'] = $pluginId;
        }

        $this->resolveTranslationsLanguageIds($data);

        $this->paymentMethodRepository->upsert(\array_values($data), $context);
    }

    protected function deleteByIdentifiers(array $identifiers, Context $context): void
    {
        // do not delete payment methods
    }

    protected function getBundleDataDir(Plugin|AdditionalBundle $bundle): string
    {
        return $bundle->getPaymentMethodsPath();
    }

    protected function getObsoleteIdentifiers(Plugin|AdditionalBundle $bundle): array
    {
        return [];
    }

    protected function removeCreateOnlyFields(&$paymentMethod): void
    {
        foreach(['position', 'active', 'afterOrderEnabled', 'media'] as $field) {
            unset($paymentMethod[$field]);
        }

        foreach($paymentMethod['translations'] ?? [] as &$translation) {
            foreach(['name', 'description'] as $field) {
                unset($translation[$field]);
            }
        }
    }

    protected function getMediaFolderId(Context $context): ?string
    {
        if($this->mediaFolderId === null) {
            $folderId = $this->mediaInstaller->getMediaDefaultFolderId(PaymentMethodDefinition::ENTITY_NAME, $context);
            $this->mediaFolderId = $folderId ?? false;
        }

        return $this->mediaFolderId === false ? null : $this->mediaFolderId;
    }

    protected function getPluginId(ShopwarePlugin $plugin): ?string
    {
        return $this->connection->fetchOne(
            'SELECT LOWER(HEX(`id`)) FROM `plugin` WHERE `name` = :pluginName',
            ['pluginName' => $plugin->getName()]
        );
    }

}