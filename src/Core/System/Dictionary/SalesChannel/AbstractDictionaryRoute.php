<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\SalesChannel;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all active dictionary entries of one dictionary type.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
#[Package('fundamentals@discovery')]
abstract class AbstractDictionaryRoute
{
    abstract public function getDecorated(): AbstractDictionaryRoute;

    abstract public function load(Request $request, SalesChannelContext $context, Criteria $criteria): DictionaryRouteResponse;
}
