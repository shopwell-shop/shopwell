<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Gateway\Context\Command\Struct;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\App\Payload\Source;
use Shopwell\Core\Framework\App\Payload\SourcedPayloadInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Struct;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
class ContextGatewayPayloadStruct extends Struct implements SourcedPayloadInterface
{
    protected Source $source;

    public function __construct(
        protected Cart $cart,
        protected SalesChannelContext $salesChannelContext,
        protected RequestDataBag $data = new RequestDataBag(),
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getData(): RequestDataBag
    {
        return $this->data;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
