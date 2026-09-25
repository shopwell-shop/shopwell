<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StoreActionStruct extends Struct
{
    protected string $label;

    protected string $externalLink;

    public function getApiAlias(): string
    {
        return 'store_action';
    }
}
