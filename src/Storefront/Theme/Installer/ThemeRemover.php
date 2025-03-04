<?php

namespace Dustin\ShopwareUtils\Storefront\Theme\Installer;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ThemeRemover
{
    private static ?self $instance = null;

    public function __construct(
        private readonly EntityRepository $themeRepository,
        private readonly EntityRepository $mediaRepository,
        private readonly EntityRepository $themeMediaRepository
    ) {}

    public static function getInstance(ContainerInterface $container): self
    {
        if(self::$instance == null) {
            self::$instance = new self(
                $container->get('theme.repository'),
                $container->get('media.repository'),
                $container->get('theme_media.repository'),
            );
        }

        return self::$instance;
    }

    public function removeTheme(Bundle $bundle, Context $context): void
    {
        $technicalName = $bundle->getName();

        $criteria = new Criteria();
        $criteria->addAssociation('dependentThemes');
        $criteria->addAssociation('previewMedia');
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->first();

        if($theme === null) {
            return;
        }

        $dependentThemes = $theme->getDependentThemes() ?? new ThemeCollection();
        $ids = [...array_values($dependentThemes->getIds()), $theme->getId()];

        $this->removeOldMedia($theme, $context);
        $this->themeRepository->delete(array_map(fn(string $id) => ['id' => $id], $ids), $context);
    }

    protected function removeOldMedia(ThemeEntity $theme, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media.themeMedia.id', $theme->getId()));
        $result = $this->mediaRepository->searchIds($criteria, $context);

        if($result->getTotal() === 0) {
            return;
        }

        $themeMediaData = \array_map(
            function (string $id) use ($theme): array {
                return ['themeId' => $theme->getId(), 'mediaId' => $id];
            },
            $result->getIds()
        );

        $this->themeMediaRepository->delete($themeMediaData, $context);

        foreach($themeMediaData as $item) {
            try {
                $this->mediaRepository->delete([['id' => $item['mediaId']]], $context);
            } catch(RestrictDeleteViolationException) {
            }
        }
    }
}