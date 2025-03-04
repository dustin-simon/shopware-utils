<?php

declare(strict_types=1);

namespace Dustin\ShopwareUtils\Core\Framework;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin as ShopwarePlugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AdditionalBundle extends Bundle
{
    use BundleTrait;

    public function install(InstallContext $installContext, ShopwarePlugin $plugin, ContainerInterface $container): void
    {
        /** @var MigrationCollectionLoader $migrationCollectionLoader */
        $migrationCollectionLoader = $container->get(MigrationCollectionLoader::class);

        $this->runMigrations($installContext, $migrationCollectionLoader);
        $this->runUpdate($container, $plugin, $installContext->getContext());
    }

    public function postInstall(InstallContext $installContext, ShopwarePlugin $plugin, ContainerInterface $container): void {}

    public function update(UpdateContext $updateContext, ShopwarePlugin $plugin, ContainerInterface $container): void
    {
        /** @var MigrationCollectionLoader $migrationCollectionLoader */
        $migrationCollectionLoader = $container->get(MigrationCollectionLoader::class);

        $this->runMigrations($updateContext, $migrationCollectionLoader);
        $this->runUpdate($container, $plugin, $updateContext->getContext());
    }

    public function postUpdate(UpdateContext $updateContext, ShopwarePlugin $plugin, ContainerInterface $container): void {}

    public function activate(ActivateContext $activateContext, ShopwarePlugin $plugin, ContainerInterface $container): void {}

    public function deactivate(DeactivateContext $deactivateContext, ShopwarePlugin $plugin, ContainerInterface $container): void {}

    public function uninstall(UninstallContext $uninstallContext, ShopwarePlugin $plugin, ContainerInterface $container): void
    {
        $this->runUninstall($container, $uninstallContext);
    }

    public function build(ContainerBuilder $container): void
    {
        $this->registerContainerFiles($container);
        $this->buildConfig($container);

        parent::build($container);
    }

    protected function runMigrations(InstallContext $context, MigrationCollectionLoader $migrationLoader): void
    {
        if($context->isAutoMigrate()) {
            $this->createMigrationCollection($migrationLoader)->migrateInPlace();
        }
    }

    protected function removeMigrations(Connection $connection): void
    {
        $class = \addcslashes($this->getMigrationNamespace(), '\\_%').'%';
        $connection->executeStatement('DELETE FROM `migration` WHERE `class` LIKE :class', ['class' => $class]);
    }

    protected function createMigrationCollection(MigrationCollectionLoader $migrationLoader): MigrationCollection
    {
        $migrationPath = $this->getMigrationPath();

        if(!\is_dir($migrationPath)) {
            return $migrationLoader->collect('null');
        }

        $name = $this->getName();

        $migrationLoader->addSource(
            new MigrationSource(
                $name,
                [$migrationPath => $this->getMigrationNamespace()]
            )
        );

        $collection = $migrationLoader->collect($name);
        $collection->sync();

        return $collection;
    }
}
