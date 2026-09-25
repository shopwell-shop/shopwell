<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\TaxProvider\Payload;

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
class TaxProviderPayload implements SourcedPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;

    private Source $source;

    public function __construct(
        private readonly Cart $cart,
        private readonly SalesChannelContext $context
    ) {
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }
}
