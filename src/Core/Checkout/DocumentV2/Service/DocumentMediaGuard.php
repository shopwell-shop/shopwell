<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\DocumentV2\Service;

use Shopwell\Core\Checkout\DocumentV2\DocumentV2Exception;
use Shopwell\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopwell\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopwell\Core\Content\Media\MediaEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('after-sales')]
final class DocumentMediaGuard implements ResetInterface
{
    private ?string $folderId = null;

    private bool $folderIdResolved = false;

    /**
     * @param EntityRepository<MediaFolderCollection> $mediaFolderRepository
     */
    public function __construct(private readonly EntityRepository $mediaFolderRepository)
    {
    }

    public function reset(): void
    {
        $this->folderId = null;
        $this->folderIdResolved = false;
    }

    public function assertServable(MediaEntity $media, Context $context): void
    {
        if ($this->isServable($media, $context)) {
            return;
        }

        throw DocumentV2Exception::documentMediaNotAllowed($media->getId());
    }

    public function isServable(MediaEntity $media, Context $context): bool
    {
        if (!$media->isPrivate()) {
            return true;
        }

        $folderId = $this->getFolderId($context);

        return $folderId !== null && $media->getMediaFolderId() === $folderId;
    }

    private function getFolderId(Context $context): ?string
    {
        if ($this->folderIdResolved) {
            return $this->folderId;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', DocumentPersister::MEDIA_FOLDER))
            ->setLimit(1);

        $this->folderId = $context->scope(
            Context::SYSTEM_SCOPE,
            fn (Context $scoped): ?string => $this->mediaFolderRepository->searchIds($criteria, $scoped)->firstId(),
        );

        $this->folderIdResolved = true;

        return $this->folderId;
    }
}
