<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Media;

use Shopwell\Core\Content\Media\File\FileSaver;
use Shopwell\Core\Content\Media\File\MediaFile;
use Shopwell\Core\Content\Media\MediaException;
use Shopwell\Core\Content\Media\MediaService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('discovery')]
class StorefrontMediaUploader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MediaService $mediaService,
        private readonly FileSaver $fileSaver,
        private readonly StorefrontMediaValidatorRegistry $validator
    ) {
    }

    /**
     * @throws StorefrontFrameworkException
     * @throws MediaException
     */
    public function upload(UploadedFile $file, string $folder, string $type, Context $context, bool $isPrivate = false): string
    {
        $this->checkValidFile($file);

        $this->validator->validate($file, $type);

        $mediaFile = new MediaFile(
            $file->getPathname(),
            $file->getMimeType() ?? '',
            $file->getClientOriginalExtension(),
            $file->getSize() ?: 0,
            Hasher::hashFile($file->getPathname(), 'md5'),
        );

        $mediaId = $this->mediaService->createMediaInFolder($folder, $context, $isPrivate);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaFile, $mediaId): void {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                pathinfo(Uuid::randomHex(), \PATHINFO_FILENAME),
                $mediaId,
                $context
            );
        });

        return $mediaId;
    }

    private function checkValidFile(UploadedFile $file): void
    {
        if (!$file->isValid()) {
            throw MediaException::invalidFile($file->getErrorMessage());
        }

        if (preg_match('/.+\.ph(p([3457s]|-s)?|t|tml)/', $file->getFilename())) {
            throw MediaException::illegalFileName($file->getFilename(), 'contains PHP related file extension');
        }
    }
}
