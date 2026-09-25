<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\File;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
abstract class AbstractFileContentValidator
{
    abstract public function getDecorated(): AbstractFileContentValidator;

    abstract public function supports(MediaFile $mediaFile): bool;

    abstract public function validate(MediaFile $mediaFile): void;
}
