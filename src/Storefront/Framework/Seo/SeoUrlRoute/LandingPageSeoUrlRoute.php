<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Seo\SeoUrlRoute;

use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\LandingPage\LandingPageEntity;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
class LandingPageSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'frontend.landing.page';
    final public const DEFAULT_TEMPLATE = '{{ landingPage.translated.url }}';

    /**
     * @internal
     */
    public function __construct(private readonly LandingPageDefinition $landingPageDefinition)
    {
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->landingPageDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true,
            'landingPageId'
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsFilter('salesChannels.id', $salesChannel->getId()));
    }

    public function getMapping(Entity $landingPage, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$landingPage instanceof LandingPageEntity) {
            throw new \InvalidArgumentException('Expected LandingPageEntity');
        }

        $landingPageJson = $landingPage->jsonSerialize();

        return new SeoUrlMapping(
            $landingPage,
            $this->getConfig()->getPrimaryKeyParameter($landingPage->getId()),
            [
                'landingPage' => $landingPageJson,
            ]
        );
    }
}
