<?php

namespace Dustin\ShopwareUtils\Core\Framework;

use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Plugin extends \Shopware\Core\Framework\Plugin
{
    use BundleTrait;

    /**
     * @var null|AdditionalBundle[]
     */
    private ?array $bundles = null;

    public function install(InstallContext $installContext): void
    {
        foreach($this->getBundles() as $bundle) {
            $bundle->install($installContext, $this, $this->container);
        }

        $this->runUpdate($this->container, $this, $installContext->getContext());
    }

    public function postInstall(InstallContext $installContext): void
    {
        foreach($this->getBundles() as $bundle) {
            $bundle->postInstall($installContext, $this, $this->container);
        }
    }

    public function update(UpdateContext $updateContext): void
    {
        foreach($this->getBundles() as $bundle) {
            $bundle->update($updateContext, $this, $this->container);
        }

        $this->runUpdate($this->container, $this, $updateContext->getContext());
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        foreach($this->getBundles() as $bundle) {
            $bundle->postUpdate($updateContext, $this, $this->container);
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        foreach($this->getBundles() as $bundle) {
            $bundle->activate($activateContext, $this, $this->container);
        }
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        foreach($this->getBundles() as $bundle) {
            $bundle->deactivate($deactivateContext, $this, $this->container);
        }
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        foreach($this->getBundles() as $bundle) {
            $bundle->uninstall($uninstallContext, $this, $this->container);
        }

        $this->runUninstall($this->container, $uninstallContext);
    }

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return $this->getBundles();
    }

    public function build(ContainerBuilder $container): void
    {
        $this->registerContainerFiles($container);
        $this->buildConfig($container);

        parent::build($container);
    }

    protected function createAdditionalBundles(): array
    {
        return [];
    }

    /**
     * @return AdditionalBundle[]
     */
    final protected function getBundles(): array
    {
        if($this->bundles === null) {
            $bundles = \array_values($this->createAdditionalBundles());

            foreach($bundles as $index => $bundle) {
                if(!$bundle instanceof AdditionalBundle) {
                    throw new \UnexpectedValueException(sprintf('Bundle #%s must be of type %s. %s given.', $index, AdditionalBundle::class, \get_debug_type($bundle)));
                }
            }

            $this->bundles = $bundles;
        }

        return $this->bundles;
    }
}