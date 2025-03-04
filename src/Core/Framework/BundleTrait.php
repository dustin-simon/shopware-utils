<?php

declare(strict_types=1);

namespace Dustin\ShopwareUtils\Core\Framework;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Dustin\ShopwareUtils\Core\Checkout\Document\Installer\DocumentTypeInstaller;
use Dustin\ShopwareUtils\Core\Checkout\Payment\Installer\PaymentMethodInstaller;
use Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer\MailTemplateInstaller;
use Dustin\ShopwareUtils\Core\Content\MailTemplate\Installer\MailTemplateTypeInstaller;
use Dustin\ShopwareUtils\Core\System\CustomField\Installer\CustomFieldInstaller;
use Dustin\ShopwareUtils\Storefront\Theme\Installer\ThemeRemover;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin as ShopwarePlugin;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Kernel;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Util\XmlUtils;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

trait BundleTrait
{
    /**
     * @return string[]
     */
    public function getTablesToRemove(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getContainerFileDirs(): array
    {
        return [
            'Core/Framework/DependencyInjection',
            'Core/DevOps/DependencyInjection',
            'Core/Maintenance/DependencyInjection',
            'Core/Profiling/DependencyInjection',
            'Core/System/DependencyInjection',
            'Core/Content/DependencyInjection',
            'Core/Checkout/DependencyInjection',
            'Administration/DependencyInjection',
            'Storefront/DependencyInjection',
            'Elasticsearch/DependencyInjection',
        ];
    }

    public function getCustomFieldsPath(): string
    {
        return $this->getPath().'/Resources/custom_fields/';
    }

    /**
     * @return string[]
     */
    public function getObsoleteCustomFieldSets(): array
    {
        return [];
    }

    public function getMailTemplateTypesPath(): string
    {
        return $this->getPath().'/Resources/mail_template_types/';
    }

    public function getObsoleteMailTemplateTypes(): array
    {
        return [];
    }

    public function getMailTemplatesPath(): string
    {
        return $this->getPath().'/Resources/mail_templates/';
    }

    public function getDocumentTypesPath(): string
    {
        return $this->getPath().'/Resources/document_types/';
    }

    public function getObsoleteDocumentTypes(): array
    {
        return [];
    }

    public function getPaymentMethodsPath(): string
    {
        return $this->getPath().'/Resources/payment_methods/';
    }

    protected function runUpdate(ContainerInterface $container, ShopwarePlugin $plugin, Context $context): void
    {
        $this->updateCustomFields($container, $plugin, $context);
        $this->updateMailTemplateTypes($container, $plugin, $context);
        $this->updateMailTemplates($container, $plugin, $context);
        $this->updateDocumentTypes($container, $plugin, $context);
        $this->updatePaymentMethods($container, $plugin, $context);
        $this->updateDefaultConfigs($container);
    }

    protected function runUninstall(ContainerInterface $container, UninstallContext $context): void
    {
        if($context->keepUserData()) {
            return;
        }

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->deleteCustomFields($container, $context->getContext());
        $this->deleteTables($connection);
        $this->deleteMailTemplateTypes($container, $context->getContext());
        $this->removeMigrations($connection);
        $this->deleteConfig($connection);
        $this->deleteDocumentTypes($container, $context->getContext());
        $this->removeTheme($container, $context->getContext());
    }

    protected function updateCustomFields(ContainerInterface $container, ShopwarePlugin $plugin, Context $context): void
    {
        CustomFieldInstaller::getInstance($container)->update($this, $plugin, $context);
    }

    protected function deleteCustomFields(ContainerInterface $container, Context $context): void
    {
        CustomFieldInstaller::getInstance($container)->delete($this, $context);
    }

    protected function updateMailTemplateTypes(ContainerInterface $container, ShopwarePlugin $plugin, Context $context): void
    {
        MailTemplateTypeInstaller::getInstance($container)->update($this, $plugin, $context);
    }

    protected function deleteMailTemplateTypes(ContainerInterface $container, Context $context): void
    {
        MailTemplateTypeInstaller::getInstance($container)->delete($this, $context);
    }

    protected function updateMailTemplates(ContainerInterface $container, ShopwarePlugin $plugin, Context $context): void
    {
        MailTemplateInstaller::getInstance($container)->update($this, $plugin, $context);
    }

    protected function updateDocumentTypes(ContainerInterface $container, ShopwarePlugin $plugin, Context $context): void
    {
        DocumentTypeInstaller::getInstance($container)->update($this, $plugin, $context);
    }

    protected function deleteDocumentTypes(ContainerInterface $container, Context $context): void
    {
        DocumentTypeInstaller::getInstance($container)->delete($this, $context);
    }

    protected function updatePaymentMethods(ContainerInterface $container, ShopwarePlugin $plugin, Context $context): void
    {
        PaymentMethodInstaller::getInstance($container)->update($this, $plugin, $context);
    }

    protected function updateDefaultConfigs(ContainerInterface $container): void
    {
        /** @var SystemConfigService $configService */
        $configService = $container->get(SystemConfigService::class);

        foreach($this->getConfigs() as $name => $config) {
            $configService->saveConfig($config, \sprintf('%s.%s', $this->getName(), $name), false);
        }
    }

    /**
     * @throws Exception
     */
    protected function deleteConfig(Connection $connection): void
    {
        $connection->executeStatement('DELETE FROM `system_config` WHERE `configuration_key` LIKE "'.$this->getName().'%"');
    }

    protected function deleteTables(Connection $connection): void
    {
        $tables = $this->getTablesToRemove();

        $tables = \array_filter(
            $tables,
            function (string $table) use ($connection): bool {
                return (bool) $connection->fetchOne(\sprintf('SHOW TABLES LIKE "%s"', $table));
            }
        );

        if(empty($tables)) {
            return;
        }

        $connection->beginTransaction();

        try {
            foreach($tables as $table) {
                $connection->executeStatement(\sprintf('DELETE FROM `%s`', $table));
            }

            $connection->commit();
        } catch(\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }

        foreach($tables as $table) {
            $connection->executeStatement(\sprintf('DROP TABLE `%s`', $table));
        }
    }

    protected function removeTheme(ContainerInterface $container, Context $context): void
    {
        ThemeRemover::getInstance($container)->removeTheme($this, $context);
    }

    protected function buildConfig(ContainerBuilder $container): void
    {
        $configDir = \rtrim($this->getPath(), '/').'/Resources/config';

        if(!\is_dir($configDir)) {
            return;
        }

        $locator = new FileLocator($configDir);

        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        $configLoader = new DelegatingLoader($resolver);
        $configLoader->load('{packages}/*'.Kernel::CONFIG_EXTS, 'glob');

        $env = $container->getParameter('kernel.environment');

        $configLoader->load('{packages}/'.$env.'/*'.Kernel::CONFIG_EXTS, 'glob');
    }

    protected function registerContainerFiles(ContainerBuilder $container): void
    {
        $dirs = $this->getContainerFileDirs();

        if(empty($dirs)) {
            return;
        }

        $locator = new FileLocator($this->getPath());
        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
        ]);
        $loader = new DelegatingLoader($resolver);

        foreach($dirs as $dir) {
            if(!\is_string($dir)) {
                throw new \UnexpectedValueException(\sprintf('Directory must be string. Got: %s', \get_debug_type($dir)));
            }

            $dir = \trim($dir, '/');
            $path = \rtrim($this->getPath(), '/').'/'.$dir;

            if(!\is_dir($path)) {
                continue;
            }

            $expression = \sprintf($dir.'/*%s', Kernel::CONFIG_EXTS);

            $loader->load($expression, 'glob');
        }
    }

    protected function getConfigs(): array
    {
        $configDir = \rtrim($this->getPath(), '/').'/Resources/config';

        if(!\is_dir($configDir)) {
            return [];
        }

        $expression = $configDir.'/*.xml';
        $files = \glob($expression, \GLOB_BRACE);

        if(empty($files)) {
            return [];
        }

        $result = [];
        $reader = new ConfigReader();

        /** @var string $file */
        foreach($files as $file) {
            if(!\is_file($file)) {
                continue;
            }

            $configName = \pathinfo($file, PATHINFO_FILENAME);

            if(\in_array($configName, ['routes', 'services'], true)) {
                continue;
            }

            if($this->isBundleConfigFile($file)) {
                $config = $reader->getConfigFromBundle($this, $configName);
                $result[$configName] = $config;
            }
        }

        return $result;
    }

    protected function isBundleConfigFile(string $file): bool
    {
        $dom = XmlUtils::loadFile($file);
        $root = $dom->documentElement->tagName;

        if($root !== 'config') {
            return false;
        }

        $cards = $dom->getElementsByTagName('card');

        return \count($cards) > 0;
    }

}