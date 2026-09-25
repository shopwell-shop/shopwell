<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StoreLicenseTypeStruct extends Struct
{
    protected string $name;

    public function getApiAlias(): string
    {
        return 'store_license_type';
    }
}
