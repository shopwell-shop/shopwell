<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Api;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Sms\Gateway\Audit\SmsGatewayAuditCapabilities;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayRegistry;
use Shopwell\Core\System\Sms\Gateway\SmsGatewayTransportFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Tells the administration which sms providers this shop can be configured with, which credentials
 * each of them needs, and whether it takes part in the audit of a template.
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
 * The audit block says whether a message over the provider needs an approved template, whether the
 * template can be reported over the api, whether the state can be read back, and which fields the
 * submission form has - a provider that cannot submit answers with no fields and no button.
 *
 * @internal
 */
#[Package('framework')]
class SmsGatewayProviderController extends AbstractController
{
    /**
     * The audit block of a provider the capabilities do not describe, which cannot happen because
     * both read the same registry - kept so the shape of the payload does not depend on it.
     */
    private const NO_AUDIT = [
        'requiresApproval' => false,
        'canSubmit' => false,
        'canReadStatus' => false,
        'submissionFields' => [],
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly SmsGatewayRegistry $gatewayRegistry,
        private readonly SmsGatewayAuditCapabilities $capabilities,
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
        $audit = [];

        foreach ($this->capabilities->describeAll() as $described) {
            $audit[$described['technicalName']] = [
                'requiresApproval' => $described['requiresApproval'],
                'canSubmit' => $described['canSubmit'],
                'canReadStatus' => $described['canReadStatus'],
                'submissionFields' => $described['submissionFields'],
            ];
        }

        $providers = array_map(
            fn (SmsGatewayTransportFactoryInterface $factory): array => [
                'technicalName' => $factory->getTechnicalName(),
                'labelSnippet' => $factory->getLabelSnippet(),
                'supportedRegions' => $factory->getSupportedRegions(),
                'supportsTemplateMode' => $factory->supportsTemplateMode(),
                'credentialDefinitions' => $factory->getCredentialDefinitions(),
                'audit' => $audit[$factory->getTechnicalName()] ?? self::NO_AUDIT,
            ],
            array_values($this->gatewayRegistry->all())
        );

        return new JsonResponse([
            'providers' => $providers,
            // the five states with their snippet keys, so the administration keeps no list of its own
            'approvalStatuses' => $this->capabilities->approvalStatusOptions(),
        ]);
    }
}
