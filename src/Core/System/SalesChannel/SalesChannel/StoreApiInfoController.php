<?php declare(strict_types=1);

namespace Shopwell\Core\System\SalesChannel\SalesChannel;

use Shopwell\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopwell\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopwell\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopwell\Core\Framework\Api\Route\RouteInfo;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Routing\StoreApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Package('discovery')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class StoreApiInfoController
{
    /**
     * @internal
     *
     * @param array{administration?: string} $cspTemplates
     */
    public function __construct(
        protected DefinitionService $definitionService,
        private readonly Environment $twig,
        private readonly array $cspTemplates,
        private readonly ApiRouteInfoResolver $apiRouteInfoResolver,
    ) {
    }

    #[Route(
        path: '/store-api/_info/openapi3.json',
        name: 'store-api.info.openapi3',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function info(Request $request): JsonResponse
    {
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);

        $apiType = $this->definitionService->toApiType($apiType);
        if ($apiType === null) {
            throw RoutingException::invalidRequestParameter('type');
        }

        $data = $this->definitionService->generate(OpenApi3Generator::FORMAT, DefinitionService::STORE_API, $apiType);

        return new JsonResponse($data);
    }

    #[Route(
        path: '/store-api/_info/open-api-schema.json',
        name: 'store-api.info.open-api-schema',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function openApiSchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(OpenApi3Generator::FORMAT, DefinitionService::STORE_API);

        return new JsonResponse($data);
    }

    #[Route(
        path: '/store-api/_info/stoplightio.html',
        name: 'store-api.info.stoplightio',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function stoplightIoInfoHtml(Request $request): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);
        $response = new Response($this->twig->render(
            '@Framework/stoplightio.html.twig',
            [
                'schemaUrl' => 'store-api.info.openapi3',
                'cspNonce' => $nonce,
                'apiType' => $apiType,
            ]
        ));

        $cspTemplate = $this->cspTemplates['administration'] ?? '';
        $cspTemplate = trim($cspTemplate);
        if ($cspTemplate !== '') {
            $csp = str_replace('%nonce%', $nonce, $cspTemplate);
            $csp = str_replace(["\n", "\r"], ' ', $csp);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    #[Route(
        path: '/store-api/_info/routes',
        name: 'store-api.info.routes',
        defaults: ['auth_required' => '%shopware.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function getRoutes(): JsonResponse
    {
        $endpoints = array_map(
            static fn (RouteInfo $endpoint) => ['path' => $endpoint->path, 'methods' => $endpoint->methods],
            $this->apiRouteInfoResolver->getApiRoutes(StoreApiRouteScope::ID)
        );

        return new JsonResponse(['endpoints' => $endpoints]);
    }
}
