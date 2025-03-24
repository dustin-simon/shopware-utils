<?php

namespace Dustin\ShopwareUtils\Core\Framework\Installer;

use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class MediaInstaller
{
    public function __construct(
        protected readonly EntityRepository $mediaRepository,
        protected readonly EntityRepository $mediaFolderRepository,
        protected readonly FileSaver $fileSaver,
    ) {}

    public function installMediaFile(string $file, ?string $folderId, Context $context): string
    {
        if(!\is_file($file)) {
            throw new FileNotFoundException($file);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', \pathinfo($file, PATHINFO_FILENAME)));
        $id = $this->mediaRepository->searchIds($criteria, $context)->firstId() ?? Uuid::randomHex();

        $this->mediaRepository->upsert([[
            'id' => $id,
            'private' => false,
            'mediaFolderId' => $folderId,
        ]], $context);

        $mediaFile = $this->createMediaFile($file);

        $this->fileSaver->persistFileToMedia(
            $mediaFile,
            \pathinfo($file, \PATHINFO_FILENAME),
            $id,
            $context
        );

        return $id;
    }

    public function getMediaDefaultFolderId(string $entity, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', $entity));
        $criteria->setLimit(1);

        return $this->mediaFolderRepository->searchIds($criteria, $context)->firstId();
    }

    protected function createMediaFile(string $file): MediaFile
    {
        return new MediaFile(
            $file,
            \mime_content_type($file),
            \pathinfo($file, \PATHINFO_EXTENSION),
            \filesize($file)
        );
    }

}