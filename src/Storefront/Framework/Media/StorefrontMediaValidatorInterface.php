<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Media;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[Package('discovery')]
interface StorefrontMediaValidatorInterface
{
    /**
     * Returns the supported file type
     */
    public function getType(): string;

    /**
     * Validates the provided file
     */
    public function validate(UploadedFile $file): void;
}
