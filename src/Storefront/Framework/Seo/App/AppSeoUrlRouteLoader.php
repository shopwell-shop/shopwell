<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Seo\App;

use Shopwell\Core\Content\Seo\ConfiguredEntitySeoUrlRoute;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteLoaderInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class AppSeoUrlRouteLoader implements SeoUrlRouteLoaderInterface
{
    public function __construct(
        private readonly AppSeoUrlRouteProvider $routes,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public function load(): iterable
    {
        foreach ($this->routes->getEntityRoutes() as $seoUrl) {
            if (!$this->definitionRegistry->has($seoUrl->getEntityName())) {
                continue;
            }

            yield new ConfiguredEntitySeoUrlRoute(new AppSeoUrlRoute(
                $this->definitionRegistry->getByEntityName($seoUrl->getEntityName()),
                $seoUrl,
            ));
        }
    }
}
