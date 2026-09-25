<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopwell\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopwell\Core\Framework\Log\Package;

/**
 * Type render data whose public field intentionally shadows a shared `config.*` key.
 *
 * @internal
 */
#[Package('after-sales')]
readonly class CollidingRenderData extends AbstractRenderData
{
    public function __construct(
        public string $companyName = 'shadowed',
    ) {
    }
}
