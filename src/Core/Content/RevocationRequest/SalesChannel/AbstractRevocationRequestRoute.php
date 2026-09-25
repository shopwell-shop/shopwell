<?php declare(strict_types=1);

namespace Shopwell\Core\Content\RevocationRequest\SalesChannel;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('after-sales')]
abstract class AbstractRevocationRequestRoute
{
    abstract public function getDecorated(): AbstractRevocationRequestRoute;

    abstract public function request(RequestDataBag $dataBag, SalesChannelContext $context): RevocationRequestRouteResponse;
}
