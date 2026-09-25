<?php

declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller\Stub;

use Shopwell\Core\PlatformRequest;
use Shopwell\Storefront\Controller\ProductController;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Tests\Unit\Storefront\Controller\StorefrontControllerMockTrait;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class ProductControllerStub extends ProductController
{
    use StorefrontControllerMockTrait;
}
