<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
interface MediaUrlPlaceholderHandlerInterface
{
    public function replace(string $content): string;
}
