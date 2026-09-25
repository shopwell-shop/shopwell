<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Checkout\Confirm;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Checkout\Payment\PaymentMethodCollection;
use Shopwell\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class CheckoutConfirmPage extends Page
{
    protected Cart $cart;

    protected PaymentMethodCollection $paymentMethods;

    protected ShippingMethodCollection $shippingMethods;

    protected bool $showRevocation = false;

    protected bool $hideShippingAddress = false;

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function getPaymentMethods(): PaymentMethodCollection
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(PaymentMethodCollection $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function isShowRevocation(): bool
    {
        return $this->showRevocation;
    }

    public function setShowRevocation(bool $showRevocation): void
    {
        $this->showRevocation = $showRevocation;
    }

    public function isHideShippingAddress(): bool
    {
        return $this->hideShippingAddress;
    }

    public function setHideShippingAddress(bool $hideShippingAddress): void
    {
        $this->hideShippingAddress = $hideShippingAddress;
    }
}
