<?php declare(strict_types=1);

namespace Shopwell\Administration\Framework\Routing;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\AbstractRouteScope;
use Shopwell\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
class AdministrationRouteScope extends AbstractRouteScope implements ApiContextRouteScopeDependant
{
    final public const ID = 'administration';
    final public const ALLOWED_PATH = 'admin';

    /**
     * @internal
     */
    public function __construct(string $administrationPathName = self::ALLOWED_PATH)
    {
        $this->allowedPaths = [$administrationPathName, ApiRouteScope::ALLOWED_PATH];
    }

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
