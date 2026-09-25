<?php declare(strict_types=1);

namespace Shopwell\Core\Content\LandingPage\SalesChannel;

use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<LandingPageEntity>
 */
#[Package('discovery')]
class LandingPageRouteResponse extends StoreApiResponse
{
    public function getLandingPage(): LandingPageEntity
    {
        return $this->object;
    }
}
