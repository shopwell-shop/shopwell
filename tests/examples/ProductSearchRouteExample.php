<?php declare(strict_types=1);

namespace Shopwell\Tests\Examples;

use Shopwell\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Shopwell\Core\Content\Product\ProductCollection;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopwell\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Resolves the product search yourself — e.g. against an external search service —
 * instead of the core listing-loader based search.
 */
readonly class ProductSearchRouteExample implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductSearchRouteExtension::NAME . '.pre' => 'replace',
        ];
    }

    public function replace(ProductSearchRouteExtension $event): void
    {
        // The request is exposed through the public properties:
        // $event->request, $event->context, $event->criteria

        $result = new ProductListingResult(
            ProductDefinition::ENTITY_NAME,
            0,
            new ProductCollection(),
            null,
            $event->criteria,
            $event->context->getContext(),
        );

        $event->result = new ProductSearchRouteResponse($result);

        // stop propagation so the core product search is skipped
        $event->stopPropagation();
    }
}
