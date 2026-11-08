<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ContentSystem\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<Struct>
 */
#[Package('framework')]
abstract class AbstractContentRouteResponse extends StoreApiResponse
{
}
