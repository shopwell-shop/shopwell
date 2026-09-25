<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Product\SalesChannel\Garan;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{svg: string|null, nestedSvg: string|null}>>
 */
#[Package('inventory')]
class GaranLabelRouteResponse extends StoreApiResponse
{
    public function __construct(?string $svg, ?string $nestedSvg = null)
    {
        parent::__construct(new ArrayStruct(['svg' => $svg, 'nestedSvg' => $nestedSvg], 'garan_label'));
    }
}
