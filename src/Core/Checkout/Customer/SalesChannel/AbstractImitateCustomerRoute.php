<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Customer\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractImitateCustomerRoute
{
    abstract public function getDecorated(): AbstractImitateCustomerRoute;

    abstract public function imitateCustomerLogin(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse;
}
