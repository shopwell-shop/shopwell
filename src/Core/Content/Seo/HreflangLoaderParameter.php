<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Seo;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class HreflangLoaderParameter
{
    /**
     * @param array<string, mixed> $routeParameters
     */
    public function __construct(
        protected string $route,
        protected array $routeParameters,
        protected SalesChannelContext $salesChannelContext,
        private readonly bool $homepage = false,
        private readonly string $basePath = '',
    ) {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function isHomepage(): bool
    {
        return $this->homepage;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }
}
