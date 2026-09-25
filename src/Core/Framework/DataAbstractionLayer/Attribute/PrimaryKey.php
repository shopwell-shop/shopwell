<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Attribute;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class PrimaryKey
{
}
