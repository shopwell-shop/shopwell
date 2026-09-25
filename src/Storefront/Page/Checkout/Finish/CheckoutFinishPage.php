<?php declare(strict_types=1);

namespace Shopwell\Storefront\Page\Checkout\Finish;

use Shopwell\Core\Checkout\Order\OrderEntity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Storefront\Page\Page;

#[Package('checkout')]
class CheckoutFinishPage extends Page
{
    protected OrderEntity $order;

    protected bool $changedPayment = false;

    protected bool $paymentFailed = false;

    protected bool $logoutCustomer = false;

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function isChangedPayment(): bool
    {
        return $this->changedPayment;
    }

    public function setChangedPayment(bool $changedPayment): void
    {
        $this->changedPayment = $changedPayment;
    }

    public function isPaymentFailed(): bool
    {
        return $this->paymentFailed;
    }

    public function setPaymentFailed(bool $paymentFailed): void
    {
        $this->paymentFailed = $paymentFailed;
    }

    public function isLogoutCustomer(): bool
    {
        return $this->logoutCustomer;
    }

    public function setLogoutCustomer(bool $logoutCustomer): void
    {
        $this->logoutCustomer = $logoutCustomer;
    }
}
