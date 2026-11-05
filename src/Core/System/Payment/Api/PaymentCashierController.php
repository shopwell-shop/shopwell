<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\Cashier\PaymentCashierService;
use Shopwell\Core\System\Payment\PaymentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The signed app API is the trust boundary for payer identity and frontend capabilities.
 * Browser consumers derive their own context and never submit trusted SDK identities here.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID]])]
class PaymentCashierController extends AbstractPaymentApiController
{
    public function __construct(PaymentAppAuthenticator $authenticator, private readonly PaymentCashierService $cashier, private readonly PaymentContextParser $contexts)
    {
        parent::__construct($authenticator);
    }

    #[Route(path: '/payment-api/v1/cashier-sessions', name: 'payment-api.cashier-session.create', methods: [Request::METHOD_POST])]
    public function create(Request $request): JsonResponse
    {
        [$app, $payload] = $this->authenticate($request);
        $profile = $this->requiredString($payload, 'profile');
        $context = $this->contexts->parse($payload);
        $session = $this->cashier->create($app, $profile, $payload, $context);

        return $this->json($this->cashier->present($app, $session));
    }

    #[Route(path: '/payment-api/v1/cashier-sessions/{sessionId}', name: 'payment-api.cashier-session.get', methods: [Request::METHOD_GET])]
    public function get(Request $request, string $sessionId): JsonResponse
    {
        [$app] = $this->authenticate($request);

        return $this->json($this->cashier->present($app, $this->cashier->get($app, $sessionId)));
    }

    #[Route(path: '/payment-api/v1/cashier-sessions/{sessionId}/pay', name: 'payment-api.cashier-session.pay', methods: [Request::METHOD_POST])]
    public function pay(Request $request, string $sessionId): JsonResponse
    {
        [$app, $payload] = $this->authenticate($request);

        return $this->json($this->cashier->pay($app, $sessionId, $this->requiredString($payload, 'method')));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (!\is_string($value) || trim($value) === '') {
            throw PaymentException::missingParameter($key);
        }

        return trim($value);
    }
}
