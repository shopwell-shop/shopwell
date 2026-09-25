<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Controller;

use League\OAuth2\Server\Exception\OAuthServerException;
use Shopwell\Core\Framework\Api\ApiException;
use Shopwell\Core\Framework\Api\HealthCheck\Event\HealthCheckEvent;
use Shopwell\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopwell\Core\Framework\SystemCheck\SystemChecker;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class HealthCheckController
{
    public const HEADER_AUTHORIZATION = 'Authorization';

    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemChecker $systemChecker,
        private readonly SymfonyBearerTokenValidator $tokenValidator,
        private readonly ?string $staticToken,
    ) {
    }

    /**
     * This is a simple health check to check that the basic application runs.
     * Use it in Docker HEALTHCHECK with curl --silent --fail http://localhost/api/_info/health-check
     */
    #[Route(path: '/api/_info/health-check', name: 'api.info.health.check', defaults: ['auth_required' => false], methods: ['GET'])]
    public function check(Context $context): Response
    {
        $event = new HealthCheckEvent($context);
        $this->eventDispatcher->dispatch($event);

        $response = new Response('');
        $response->setPrivate();

        return $response;
    }

    #[Route(path: '/api/_info/system-health-check', name: 'api.info.system-health.check', defaults: ['auth_required' => false], methods: ['GET'])]
    public function health(Request $request): Response
    {
        $this->validateStaticOrBearerAuthorization($request);

        $executionContextRaw = $request->query->getString('context', SystemCheckExecutionContext::WEB->value);
        $executionContext = SystemCheckExecutionContext::tryFrom($executionContextRaw);
        if (!$executionContext instanceof SystemCheckExecutionContext) {
            throw ApiException::badRequest('Invalid execution context: ' . $executionContextRaw);
        }

        $result = $this->systemChecker->check($executionContext);

        return (new JsonResponse(['checks' => $result]))->setPrivate();
    }

    /**
     * Validates Authorization header for either a 'Static' token or 'Bearer' token that is valid
     * Otherwise throws exception.
     *
     * @throws OAuthServerException
     */
    private function validateStaticOrBearerAuthorization(Request $request): void
    {
        $authorizationHeader = $request->headers->get(self::HEADER_AUTHORIZATION);
        if (
            $this->staticToken !== null
            && $this->staticToken !== ''
            && $authorizationHeader !== null
            && str_contains($authorizationHeader, 'Static')
        ) {
            $token = \trim((string) \preg_replace('/^\s*Static\s/', '', $authorizationHeader));

            // compare header token against configured static token
            if ($token !== $this->staticToken) {
                throw OAuthServerException::accessDenied('Static token is invalid');
            }
        } else {
            $this->tokenValidator->validateAuthorization($request);
        }
    }
}
