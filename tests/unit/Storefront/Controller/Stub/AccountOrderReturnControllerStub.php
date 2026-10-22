<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller\Stub;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\PlatformRequest;
use Shopwell\Storefront\Controller\AccountOrderReturnController;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Tests\Unit\Storefront\Controller\StorefrontControllerRecorder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Package('after-sales')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class AccountOrderReturnControllerStub extends AccountOrderReturnController
{
    private ?StorefrontControllerRecorder $recorder = null;

    public function recorder(): StorefrontControllerRecorder
    {
        return $this->recorder ??= new StorefrontControllerRecorder();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        return $this->recorder()->renderStorefront($view, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->recorder()->redirectToRoute($route, $parameters, $status);
    }

    protected function hook(Hook $hook): void
    {
        $this->recorder()->hook($hook);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $snippet, array $parameters = []): string
    {
        return $snippet;
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->recorder()->addFlash($type, $message);
    }
}
