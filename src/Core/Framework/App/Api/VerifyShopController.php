<?php

declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Api;

use Shopwell\Core\Framework\App\Api\DTO\VerifyShop;
use Shopwell\Core\Framework\App\Url\AppUrlVerifier;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\RateLimiter\RateLimiter;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class VerifyShopController
{
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly AppUrlVerifier $appUrlVerifier
    ) {
    }

    #[Route(
        path: 'api/app-system/shop/verify',
        name: 'api.app_system.shop_verify',
        defaults: ['auth_required' => false],
        methods: ['GET']
    )]
    public function verify(#[MapQueryString] VerifyShop $verifyShopRequest, Request $request): Response
    {
        $ip = $request->getClientIp();
        if ($ip === null) {
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);
        }

        $this->rateLimiter->ensureAccepted(RateLimiter::APP_SHOP_VERIFY, $ip);

        if ($this->appUrlVerifier->completeVerification($verifyShopRequest->runId, $verifyShopRequest->token)) {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        return new JsonResponse([], Response::HTTP_BAD_REQUEST);
    }
}
