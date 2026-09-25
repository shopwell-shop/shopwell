<?php declare(strict_types=1);

namespace Shopwell\Storefront\Controller;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class VerificationHashController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    #[Route(path: '/sw-domain-hash.html', name: 'api.verification-hash.load', defaults: ['auth_required' => false], methods: ['GET'])]
    public function load(): Response
    {
        $verificationHash = $this->systemConfigService->getString('core.store.verificationHash');

        return new Response(
            $verificationHash,
            ($verificationHash === '') ? Response::HTTP_NOT_FOUND : Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }
}
