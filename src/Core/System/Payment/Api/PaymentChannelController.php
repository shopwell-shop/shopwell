<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Payment\App\PaymentAppAuthenticator;
use Shopwell\Core\System\Payment\Provider\PaymentConfigResolver;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tells an app which channels it may use, so it can build its own payment selection.
 *
 * A channel is only listed when the app is authorized for the payment interface behind it *and* the
 * channel itself is enabled for the app, because the app cannot pay with one without the other.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [PaymentApiRouteScope::ID]])]
class PaymentChannelController extends AbstractPaymentApiController
{
    public function __construct(
        PaymentAppAuthenticator $authenticator,
        private readonly PaymentConfigResolver $configResolver,
    ) {
        parent::__construct($authenticator);
    }

    #[Route(
        path: '/payment-api/v1/channels',
        name: 'payment-api.channel.list',
        methods: [Request::METHOD_GET]
    )]
    public function list(Request $request): JsonResponse
    {
        [$app] = $this->authenticate($request);

        return $this->json(['channels' => $this->configResolver->enabledFor($app)]);
    }
}
