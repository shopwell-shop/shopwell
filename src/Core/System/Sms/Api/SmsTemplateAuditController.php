<?php declare(strict_types=1);

namespace Shopwell\Core\System\Sms\Api;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\PlatformRequest;
use Shopwell\Core\System\Sms\SmsException;
use Shopwell\Core\System\Sms\Template\Aggregate\SmsTemplateMapping\SmsTemplateMappingEntity;
use Shopwell\Core\System\Sms\Template\Audit\SmsTemplateAuditService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The three steps of the audit loop of one mapping row, as the administration offers them: report the
 * template at the provider, read its state back, and record a state an operator read at the console.
 *
 * The controller adds nothing to the service behind it - no validation, no decision, no provider
 * name. Its whole job is to turn the row into the one answer shape the three steps share, and a
 * failure into the same shape with the code of the reason instead of a thrown exception: the
 * administration has one branch for all three buttons and shows the message the shop gives it.
 *
 * `status` and `templateCode` are null on a failure, because the shop did not learn them - the row
 * keeps whatever it had, and the administration reloads it. Saying `unknown` would claim the shop
 * looked, which is the one thing it did not do: `unknown` is a state a provider answers with, so it
 * travels through this controller as the successful answer it is, on a 200 and with no error code.
 *
 * @internal
 */
#[Package('framework')]
class SmsTemplateAuditController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SmsTemplateAuditService $auditService,
    ) {
    }

    /**
     * Reports the template of the row at the provider it points to.
     *
     * The field values the provider's form asks for travel under `fieldValues`, so they cannot
     * collide with parameters the step itself may need later.
     */
    #[Route(
        path: '/api/_action/sms-template-audit/{mappingId}/submit',
        name: 'api.action.sms-template-audit.submit',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['sms_template_mapping:update']],
        methods: [Request::METHOD_POST]
    )]
    public function submit(Request $request, string $mappingId): JsonResponse
    {
        try {
            $mapping = $this->auditService->submit($this->contextOf($request), $mappingId, $this->fieldValuesOf($request));
        } catch (SmsException $exception) {
            return $this->failed($exception);
        }

        return $this->answered($mapping);
    }

    /**
     * Reads the state of the reported template back, for a provider that can be asked for it.
     */
    #[Route(
        path: '/api/_action/sms-template-audit/{mappingId}/refresh',
        name: 'api.action.sms-template-audit.refresh',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['sms_template_mapping:update']],
        methods: [Request::METHOD_POST]
    )]
    public function refresh(Request $request, string $mappingId): JsonResponse
    {
        try {
            $mapping = $this->auditService->refresh($this->contextOf($request), $mappingId);
        } catch (SmsException $exception) {
            return $this->failed($exception);
        }

        return $this->answered($mapping);
    }

    /**
     * Records the state an operator read at the console of a provider that cannot be asked.
     *
     * A provider that reports its state itself answers here with the code of the reason, so the
     * administration can offer the refresh of that row instead of a field it must not fill in.
     */
    #[Route(
        path: '/api/_action/sms-template-audit/{mappingId}/confirm',
        name: 'api.action.sms-template-audit.confirm',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['administration'], PlatformRequest::ATTRIBUTE_ACL => ['sms_template_mapping:update']],
        methods: [Request::METHOD_POST]
    )]
    public function confirm(Request $request, string $mappingId): JsonResponse
    {
        $payload = $request->request->all();
        $status = $payload['status'] ?? null;
        $note = $payload['note'] ?? null;

        try {
            // a value that is not a string is handed on as empty, so the service reports it as the
            // violation of the confirmation it is instead of failing on the type
            $mapping = $this->auditService->confirmManually(
                $this->contextOf($request),
                $mappingId,
                \is_string($status) ? $status : '',
                \is_string($note) ? $note : '',
            );
        } catch (SmsException $exception) {
            return $this->failed($exception);
        }

        return $this->answered($mapping);
    }

    private function answered(SmsTemplateMappingEntity $mapping): JsonResponse
    {
        return new JsonResponse([
            'status' => $mapping->auditStatus,
            'templateCode' => $mapping->templateCode,
            'errorCode' => null,
            'errorMessage' => null,
        ]);
    }

    /**
     * The message of the exception is already the rendered one, the framework substitutes the
     * placeholders before the exception leaves the service.
     */
    private function failed(SmsException $exception): JsonResponse
    {
        return new JsonResponse([
            'status' => null,
            'templateCode' => null,
            'errorCode' => $exception->getErrorCode(),
            'errorMessage' => $exception->getMessage(),
        ], $exception->getStatusCode());
    }

    /**
     * The context of the request, resolved by the api middleware before the action runs - without it
     * the row may not be read at all, so falling back to a default context would be a silent read of
     * data the caller did not ask for.
     *
     * @throws SmsException
     */
    private function contextOf(Request $request): Context
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if (!$context instanceof Context) {
            throw SmsException::missingContext((string) $request->attributes->get('_route', ''));
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldValuesOf(Request $request): array
    {
        $values = $request->request->all()['fieldValues'] ?? [];

        if (!\is_array($values)) {
            return [];
        }

        $fieldValues = [];

        foreach ($values as $name => $value) {
            if (\is_string($name)) {
                $fieldValues[$name] = $value;
            }
        }

        return $fieldValues;
    }
}
