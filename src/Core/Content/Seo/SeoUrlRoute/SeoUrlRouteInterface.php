<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo\SeoUrlRoute;

use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
interface SeoUrlRouteInterface extends EntitySeoUrlRouteInterface
{
    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping;
}
