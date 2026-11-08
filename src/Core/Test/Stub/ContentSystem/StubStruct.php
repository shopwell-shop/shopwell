<?php declare(strict_types=1);

namespace Shopwell\Core\Test\Stub\ContentSystem;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @final
 */
#[Package('framework')]
class StubStruct extends Struct
{
    public function getApiAlias(): string
    {
        return 'test_struct';
    }
}
