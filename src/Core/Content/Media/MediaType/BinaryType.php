<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\MediaType;

use Shopwell\Core\Framework\Log\Package;

#[Package('discovery')]
class BinaryType extends MediaType
{
    protected string $name = 'BINARY';
}
