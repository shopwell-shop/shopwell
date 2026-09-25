<?php declare(strict_types=1);

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Storefront\Controller\StorefrontController;
use Symfony\Component\Routing\Attribute\Route;

#[Package('framework')]
#[Route(defaults: false)]
class InvalidAclRouteInRouteAttributeController extends StorefrontController
{
    #[Route(defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true])]
    public function noAcl(): void
    {
    }

    #[Route(defaults: [PlatformRequest::ATTRIBUTE_ACL => 'string here'])]
    public function aclIsNotArray(): void
    {
    }

    #[Route(defaults: [PlatformRequest::ATTRIBUTE_ACL => [null]])]
    public function aclContainInvalidValues(): void
    {
    }

    public function noAttribute(): void
    {
    }
}
