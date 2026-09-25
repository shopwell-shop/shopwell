<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Media\Validator;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Framework\Media\StorefrontMediaValidatorInterface;
use Shopwell\Storefront\Framework\StorefrontFrameworkException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('discovery')]
class StorefrontMediaImageValidator implements StorefrontMediaValidatorInterface
{
    use MimeTypeValidationTrait;

    public function getType(): string
    {
        return 'images';
    }

    public function validate(UploadedFile $file): void
    {
        $valid = $this->checkMimeType($file, [
            'jpe|jpg|jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
        ]);

        if (!$valid) {
            throw StorefrontFrameworkException::fileTypeNotAllowed($file->getMimeType() ?? '', $this->getType());
        }

        // additional mime type validation
        // we detect the mime type over the `getimagesize` extension
        $imageSize = getimagesize($file->getPath() . '/' . $file->getFilename());
        if (!isset($imageSize['mime']) || $imageSize['mime'] !== $file->getMimeType()) {
            throw StorefrontFrameworkException::fileTypeNotAllowed($file->getMimeType() ?? '', $this->getType());
        }
    }
}
