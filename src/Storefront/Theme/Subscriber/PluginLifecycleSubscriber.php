<?php

namespace Dustin\ShopwareUtils\Storefront\Theme\Subscriber;

use Dustin\ShopwareUtils\Storefront\Theme\Installer\ThemeRemover;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Event\PluginLifecycleEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeLifecycleHandler;
use Shopware\Storefront\Theme\ThemeLifecycleService;

class PluginLifecycleSubscriber extends \Shopware\Storefront\Theme\Subscriber\PluginLifecycleSubscriber
{
    public function __construct(
        private readonly StorefrontPluginRegistryInterface $_storefrontPluginRegistry,
        private readonly string $_projectDir,
        private readonly AbstractStorefrontPluginConfigurationFactory $_pluginConfigurationFactory,
        private readonly ThemeLifecycleHandler $_themeLifecycleHandler,
        private readonly ThemeLifecycleService $_themeLifecycleService,
        private readonly KernelPluginLoader $pluginLoader,
        private readonly ThemeRemover $themeRemover,
    ) {
        parent::__construct(
            $_storefrontPluginRegistry,
            $_projectDir,
            $_pluginConfigurationFactory,
            $_themeLifecycleHandler,
            $_themeLifecycleService,
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginPostActivateEvent::class => [
                ['updateThemes'],
                ['compileThemes']
            ],
            PluginPreUpdateEvent::class => [
                ['updateThemes']
            ],
            PluginPostUpdateEvent::class => [
                ['compileThemes']
            ],
            PluginPreDeactivateEvent::class => [
                ['uninstallThemes'],
                ['compileThemes']
            ],
            PluginPostDeactivationFailedEvent::class => [
                ['updateThemes'],
                ['compileThemes']
            ],
            PluginPreUninstallEvent::class => [
                ['uninstallThemes'],
                ['compileThemes']
            ],
            PluginPostUninstallEvent::class => [
                ['removeThemes']
            ]
        ];
    }

    public function updateThemes(PluginLifecycleEvent $event): void
    {
        /** @var Context $context */
        $context = $event->getContext()->getContext();

        if(!$this->shouldCompile($context) || $event->getPlugin()->getActive() === false) {
            return;
        }

        $plugin = $this->createPlugin($event->getPlugin());

        if($plugin === null) {
            return;
        }

        $params = $this->createAdditionalBundleParams();
        $bundles = [$plugin, ...$plugin->getAdditionalBundles($params)];
        $configs = new StorefrontPluginConfigurationCollection();

        /** @var Bundle $bundle */
        foreach($bundles as $bundle) {
            $config = $this->_pluginConfigurationFactory->createFromBundle($bundle);

            if($config->getIsTheme() === true) {
                $configs->add($config);
            }
        }

        $this->_themeLifecycleService->refreshThemes($context, $configs);
        $allConfigs = clone $this->_storefrontPluginRegistry->getConfigurations();

        foreach($configs as $c) {
            $allConfigs->add($c);
        }

        $hasState = $context->hasState(ThemeLifecycleHandler::STATE_SKIP_THEME_COMPILATION);
        $context->addState(ThemeLifecycleHandler::STATE_SKIP_THEME_COMPILATION);

        foreach($configs as $config) {
            $this->_themeLifecycleHandler->handleThemeInstallOrUpdate($config, $allConfigs, $context);
        }

        if($hasState === false) {
            $context->removeState(ThemeLifecycleHandler::STATE_SKIP_THEME_COMPILATION);
        }
    }

    public function uninstallThemes(PluginLifecycleEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if(!$this->shouldCompile($context)) {
            return;
        }

        $plugin = $this->createPlugin($event->getPlugin());

        if($plugin === null) {
            return;
        }

        $params = $this->createAdditionalBundleParams();
        $bundles = [$plugin, ...$plugin->getAdditionalBundles($params)];

        foreach($bundles as $bundle) {
            $config = $this->_storefrontPluginRegistry->getConfigurations()->getByTechnicalName($bundle->getName());

            if($config === null) {
                continue;
            }

            $this->_themeLifecycleHandler->deactivateTheme($config, $context);
        }
    }

    public function removeThemes(PluginPostUninstallEvent $event): void
    {
        if($event->getContext()->keepUserData()) {
            return;
        }

        $plugin = $this->createPlugin($event->getPlugin());
        $params = $this->createAdditionalBundleParams();
        $context = $event->getContext()->getContext();
        $bundles = [$plugin, ...$plugin->getAdditionalBundles($params)];

        foreach($bundles as $bundle) {
            $this->themeRemover->removeTheme($bundle, $context);
        }
    }

    public function compileThemes(PluginLifecycleEvent $event): void
    {
        $context = $event->getContext()->getContext();

        if(!$this->shouldCompile($context)) {
            return;
        }

        $this->_themeLifecycleHandler->recompileAllActiveThemes($context);
    }

    private function createPlugin(PluginEntity $pluginEntity): ?Plugin
    {
        $pluginPath = $pluginEntity->getPath() ?: '';
        $className = $pluginEntity->getBaseClass();

        if(!\is_subclass_of($className, Plugin::class)) {
            return null;
        }

        return new $className(true, $pluginPath, $this->_projectDir);
    }

    private function shouldCompile(Context $context): bool
    {
        return !$context->hasState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
    }

    private function createAdditionalBundleParams(): AdditionalBundleParameters
    {
        return new AdditionalBundleParameters(
            $this->pluginLoader->getClassLoader(),
            $this->pluginLoader->getPluginInstances(),
            []
        );
    }
}