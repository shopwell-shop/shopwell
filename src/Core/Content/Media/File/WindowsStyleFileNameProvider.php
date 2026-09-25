<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\File;

use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class WindowsStyleFileNameProvider extends FileNameProvider
{
    protected function getNextFileName(string $originalFileName, MediaCollection $relatedMedia, int $iteration): string
    {
        $suffix = $iteration === 0 ? '' : "_($iteration)";

        return $originalFileName . $suffix;
    }
}
