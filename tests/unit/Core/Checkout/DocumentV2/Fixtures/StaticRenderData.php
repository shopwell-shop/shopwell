<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopwell\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
readonly class StaticRenderData extends AbstractRenderData
{
    public function __construct(
        public string $testData = 'test',
    ) {
    }
}
