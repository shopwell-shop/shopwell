<?php declare(strict_types=1);

namespace Shopwell\Administration\Framework\Adapter\Cache\Http;

use Shopwell\Administration\Controller\AdministrationController;
use Shopwell\Administration\Framework\Routing\AdministrationRouteScope;
use Shopwell\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-subscriber - Will be removed together with the BeforeCacheControlEvent and the CacheControlListener it hooks into.
 */
#[Package('framework')]
readonly class AdministrationCacheControlListener
{
    public function __invoke(BeforeCacheControlEvent $event): void
    {
        if (!$this->isAdministrationRequest($event)) {
            return;
        }

        $event->skipCacheControl();
    }

    private function isAdministrationRequest(BeforeCacheControlEvent $event): bool
    {
        $response = $event->response;

        // Check if the response has been marked as an administration response
        if ($response->headers->get(AdministrationController::CACHE_ID_HEADER) === AdministrationController::CACHE_ID_ADMINISTRATION) {
            return true;
        }

        $request = $event->request;

        // Check route scope attribute
        if (\in_array(
            AdministrationRouteScope::ID,
            (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []),
            true
        )) {
            return true;
        }

        // Fallback: Check if the route name starts with 'administration.'
        $routeName = $request->attributes->get('_route');
        if (\is_string($routeName) && \str_starts_with($routeName, 'administration.')) {
            return true;
        }

        return false;
    }
}
