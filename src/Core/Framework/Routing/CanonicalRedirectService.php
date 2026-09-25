<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Routing;

use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\Extension\CanonicalRedirectExtension;
use Shopwell\Core\SalesChannelRequest;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class CanonicalRedirectService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $configService,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * getRedirect takes a request processed by the RequestTransformer and checks,
     * whether it points to a SEO-URL which has been superseded. In case the corresponding
     * configuration option is active, it returns a redirect response to indicate, that
     * the request should be redirected to the canonical URL.
     */
    public function getRedirect(Request $request): ?Response
    {
        return $this->extensions->publish(
            name: CanonicalRedirectExtension::NAME,
            extension: new CanonicalRedirectExtension($request),
            function: $this->_getRedirect(...),
        );
    }

    private function _getRedirect(Request $request): ?Response
    {
        // This attribute has been set by the RequestTransformer if the requested URL was superseded.
        $canonical = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK);
        $shouldRedirect = $this->configService->getBool('core.seo.redirectToCanonicalUrl');

        if ($shouldRedirect === false) {
            return null;
        }

        if (!\is_string($canonical) || $canonical === '') {
            return null;
        }

        $queryString = $request->getQueryString();

        if ($queryString) {
            $canonicalQueryString = parse_url($canonical, \PHP_URL_QUERY);

            if (!\is_string($canonicalQueryString) || $canonicalQueryString === '') {
                $canonical = \sprintf('%s?%s', $canonical, $queryString);
            }
        }

        return new RedirectResponse($canonical, Response::HTTP_MOVED_PERMANENTLY);
    }
}
