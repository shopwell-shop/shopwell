<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\ContentSystem;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('framework')]
class StubContextStruct extends Struct
{
    public function __construct(
        public readonly ?string $cover = null,
    ) {
    }

    public function getApiAlias(): string
    {
        return 'test_context_struct';
    }
}
