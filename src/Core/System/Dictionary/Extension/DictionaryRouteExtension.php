<?php declare(strict_types=1);

namespace Shopwell\Core\System\Dictionary\Extension;

use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\Dictionary\SalesChannel\DictionaryRouteResponse;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<DictionaryRouteResponse>
 */
#[Package('fundamentals@discovery')]
final class DictionaryRouteExtension extends Extension
{
    public const NAME = 'dictionary-route.load';

    /**
     * @internal Shopwell owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
    ) {
    }
}
