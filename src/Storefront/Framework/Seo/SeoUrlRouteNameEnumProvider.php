<?php declare(strict_types=1);

namespace Shopwell\Storefront\Framework\Seo;

use Shopwell\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopwell\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopwell\Core\Framework\Log\Package;

#[Package('inventory')]
class SeoUrlRouteNameEnumProvider implements FieldEnumProviderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SeoUrlRouteRegistry $seoUrlRouteRegistry
    ) {
    }

    public function isSupported(string $entity, string $fieldName): bool
    {
        return $entity === SeoUrlDefinition::ENTITY_NAME && $fieldName === 'routeName';
    }

    /**
     * {@inheritDoc}
     */
    public function getChoices(): array
    {
        $values = [];

        foreach ($this->seoUrlRouteRegistry->getSeoUrlRoutes() as $routeName => $_route) {
            $values[] = (string) $routeName;
        }

        return array_values(array_unique($values));
    }
}
