<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Store\Struct;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class StoreLicenseSubscriptionStruct extends Struct
{
    protected \DateTimeInterface $expirationDate;

    public function getApiAlias(): string
    {
        return 'store_license_subscription';
    }
}
