<?php declare(strict_types=1);

namespace Shopwell\Core\System\Salutation\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all salutation of the authenticated sales channel.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
#[Package('checkout')]
abstract class AbstractSalutationRoute
{
    abstract public function getDecorated(): AbstractSalutationRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): SalutationRouteResponse;
}
