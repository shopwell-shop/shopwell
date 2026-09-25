<?php declare(strict_types=1);

namespace Shopwell\Core\Content\ContactForm\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ContactFormRouteResponseStruct>
 */
#[Package('discovery')]
class ContactFormRouteResponse extends StoreApiResponse
{
    public function getResult(): ContactFormRouteResponseStruct
    {
        return $this->object;
    }
}
