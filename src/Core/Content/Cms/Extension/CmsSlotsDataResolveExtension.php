<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Cms\Extension;

use Shopwell\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopwell\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopwell\Core\Framework\Extensions\Extension;
use Shopwell\Core\Framework\Log\Package;

/**
 * @public This class is used as type-hint for all event listeners, so the class string is "public consumable" API
 *
 * @title Resolves the CMS slots which are used for a rendered CMS page
 *
 * @description This event enables interception of the resolution process, allowing the collection of CMS slot data and enrichment of slots by their respective CMS resolvers
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<CmsSlotCollection>
 */
#[Package('discovery')]
final class CmsSlotsDataResolveExtension extends Extension
{
    public const NAME = 'cms-slots-data.resolve';

    /**
     * @internal Shopwell owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The slot collection, which is used to determine the correct CMS resolver to collect the data and enrich the slots
         */
        public readonly CmsSlotCollection $slots,
        /**
         * @public
         *
         * @description Allows you to access to the current resolver-context
         *
         * @param ResolverContext|EntityResolverContext $resolverContext
         */
        public readonly ResolverContext $resolverContext,
    ) {
    }
}
