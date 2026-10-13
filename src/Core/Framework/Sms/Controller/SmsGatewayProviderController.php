<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sms\Controller;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\Framework\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Shopwell\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tells the administration which sms providers this shop can be configured with, and which
 * credentials each of them needs.
 *
 * A provider is code, only its credentials are data. The administration therefore asks here instead
 * of shipping a list of providers and a form of its own, which would silently drift apart from the
 * ones the shop actually supports - including the ones an extension adds.
 *
 * The fields are returned in the vocabulary the gateway factories declare them in, the
 * administration maps that onto its form fields. Only the shape is answered here, never a value: the
 * credentials of a gateway are data of the shop and are read from their repository like any other
 * row.
 *
 * @internal
 */
#[Package('framework')]
class SmsGatewayProviderController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SmsGatewayRegistry $gatewayRegistry,
    ) {
    }

    #[Route(
        path: '/api/_action/sms/gateway-providers',
        name: 'api.action.sms.gateway-providers',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['sms_gateway:read']],
        methods: [Request::METHOD_GET]
    )]
    public function list(): JsonResponse
    {
        $providers = array_map(
            fn (SmsGatewayTransportFactoryInterface $factory): array => [
                'technicalName' => $factory->getTechnicalName(),
                'labelSnippet' => $factory->getLabelSnippet(),
                'supportedRegions' => $factory->getSupportedRegions(),
                'supportsTemplateMode' => $factory->supportsTemplateMode(),
                'credentialDefinitions' => $factory->getCredentialDefinitions(),
            ],
            array_values($this->gatewayRegistry->all())
        );

        return new JsonResponse(['providers' => $providers]);
    }
}
