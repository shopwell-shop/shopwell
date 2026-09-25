<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Newsletter\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\ArrayStruct;
use Shopwell\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ArrayStruct<array{status: string, success: bool}>>
 */
#[Package('after-sales')]
class NewsletterSubscribeRouteResponse extends StoreApiResponse
{
    public function __construct(string $status)
    {
        parent::__construct(new ArrayStruct([
            'status' => $status,
            'success' => true,
        ], 'newsletter_subscribe'));
    }

    public function getStatus(): string
    {
        return $this->object->get('status');
    }
}
