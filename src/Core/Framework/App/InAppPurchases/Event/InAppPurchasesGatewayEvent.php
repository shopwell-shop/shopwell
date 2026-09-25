<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\InAppPurchases\Event;

use Shopwell\Core\Framework\App\InAppPurchases\Gateway\InAppPurchasesGateway;
use Shopwell\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched once a response is received from the app server after making a call to the
 * InAppPurchasesGateway.
 *
 * @internal
 *
 * @see InAppPurchasesGateway::process() for an example implementation
 *
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class InAppPurchasesGatewayEvent extends Event
{
    public function __construct(
        public readonly InAppPurchasesResponse $response,
    ) {
    }
}
