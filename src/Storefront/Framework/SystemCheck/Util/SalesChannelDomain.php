<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\SystemCheck\Util;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelDomain extends Struct
{
    private function __construct(
        public readonly string $salesChannelId,
        public readonly string $url,
        public readonly string $id,
        public readonly string $languageId,
        public readonly string $currencyId,
    ) {
    }

    public static function create(
        string $salesChannelId,
        string $url,
        string $id,
        string $languageId,
        string $currencyId,
    ): self {
        return new self($salesChannelId, $url, $id, $languageId, $currencyId);
    }
}
