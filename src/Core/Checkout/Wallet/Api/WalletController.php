<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Wallet\Api;

use Shopwell\Core\Checkout\Wallet\Service\WalletService;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class WalletController
{
    /**
     * @internal
     */
    public function __construct(private readonly WalletService $walletService)
    {
    }

    #[Route(
        path: '/api/_action/wallet/customer/{customerId}/top-up',
        name: 'api.action.wallet.customer.top-up',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['wallet.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function topUp(string $customerId, Request $request, Context $context): Response
    {
        $currencyId = (string) $request->request->get('currencyId');
        $amount = (float) $request->request->get('amount');
        $externalReference = $request->request->get('externalReference');

        $this->walletService->topUp(
            $customerId,
            $currencyId,
            $amount,
            \is_string($externalReference) ? $externalReference : null,
            $context
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        path: '/api/_action/wallet/customer/{customerId}/adjust',
        name: 'api.action.wallet.customer.adjust',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['wallet.editor']],
        methods: [Request::METHOD_POST]
    )]
    public function adjust(string $customerId, Request $request, Context $context): Response
    {
        $currencyId = (string) $request->request->get('currencyId');
        $amount = (float) $request->request->get('amount');
        $comment = (string) $request->request->get('comment', '');

        $this->walletService->adjust($customerId, $currencyId, $amount, $comment, $this->resolveAdministratorId($context), $context);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function resolveAdministratorId(Context $context): ?string
    {
        $source = $context->getSource();

        return $source instanceof AdminApiSource ? $source->getUserId() : null;
    }
}
