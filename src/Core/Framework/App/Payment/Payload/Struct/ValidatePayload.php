<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Payment\Payload\Struct;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\App\Payload\Source;
use Shopwell\Core\Framework\App\Payload\SourcedPayloadInterface;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\CloneTrait;
use Shopwell\Core\Framework\Struct\JsonSerializableTrait;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class ValidatePayload implements SourcedPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;
    use RemoveAppTrait;

    protected Source $source;

    /**
     * @param mixed[] $requestData
     */
    public function __construct(
        protected Cart $cart,
        protected array $requestData,
        protected SalesChannelContext $salesChannelContext
    ) {
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    /**
     * @return mixed[]
     */
    public function getRequestData(): array
    {
        return $this->requestData;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
