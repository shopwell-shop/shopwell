<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller\Stub;

use Shopwell\Core\Checkout\Cart\Cart;
use Shopwell\Core\Framework\Script\Execution\Hook;
use Shopwell\Core\PlatformRequest;
use Shopwell\Storefront\Controller\SmsLoginController;
use Shopwell\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopwell\Tests\Unit\Storefront\Controller\StorefrontControllerRecorder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The controller without its container: every seam of the base class is answered by the recorder, so a
 * test reads what the controller decided instead of what the container would have rendered.
 *
 * The class repeats the route scope of the controller it extends: a route scope is not inherited, so
 * without it the analyser reads the actions of the base class as actions of this class that nobody
 * scoped. The stub is not registered as a controller, so the route itself is never exposed.
 *
 * @internal
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
class SmsLoginControllerStub extends SmsLoginController implements ResetInterface
{
    private ?StorefrontControllerRecorder $recorder = null;

    public function recorder(): StorefrontControllerRecorder
    {
        return $this->recorder ??= new StorefrontControllerRecorder();
    }

    public function reset(): void
    {
        $this->recorder()->reset();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderStorefront(string $view, array $parameters = []): Response
    {
        return $this->recorder()->renderStorefront($view, $parameters);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    protected function forwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        return $this->recorder()->forwardToRoute($routeName, $attributes, $routeParameters);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        return $this->recorder()->redirectToRoute($route, $parameters, $status);
    }

    protected function createActionResponse(Request $request): Response
    {
        return $this->recorder()->createActionResponse($request);
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

    protected function addCartErrors(Cart $cart, ?\Closure $filter = null): void
    {
        // the cart of the login has no errors in these tests
    }
}
