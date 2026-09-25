<?php declare(strict_types=1);

namespace Shopwell\Core\System\Currency\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Currency\CurrencyCollection;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CurrencyCollection>
 */
#[Package('fundamentals@framework')]
class CurrencyRouteResponse extends StoreApiResponse
{
    public function getCurrencies(): CurrencyCollection
    {
        return $this->object;
    }
}
