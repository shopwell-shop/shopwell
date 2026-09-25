<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart;

use Shopwell\Core\Checkout\Cart\Event\CartContextHashEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Hasher;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartContextHasher
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function isMatching(string $hash, Cart $cart, SalesChannelContext $context): bool
    {
        return $hash === $this->generate($cart, $context);
    }

    /**
     * @throws \JsonException
     */
    public function generate(Cart $cart, SalesChannelContext $context): string
    {
        $struct = new CartContextHashStruct();

        $struct->setPrice($cart->getPrice()->getRawTotal());
        $struct->setShippingMethod($context->getShippingMethod()->getId());
        $struct->setPaymentMethod($context->getPaymentMethod()->getId());

        foreach ($cart->getLineItems()->getElements() as $item) {
            $struct->addLineItem($item->getId(), $item->getHashContent());
        }

        $event = $this
            ->eventDispatcher
            ->dispatch(new CartContextHashEvent($context, $cart, $struct));

        return Hasher::hash($event->getHashStruct(), 'sha256');
    }
}
