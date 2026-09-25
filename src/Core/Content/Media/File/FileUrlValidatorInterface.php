<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\File;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface FileUrlValidatorInterface
{
    public function isValid(string $source): bool;
}
