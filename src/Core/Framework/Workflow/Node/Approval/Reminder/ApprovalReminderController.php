<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Reminder;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\ApiRouteScope;
use Shopwell\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\Workflow\Node\Approval\Reminder\ApprovalReminderApiTest
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
final readonly class ApprovalReminderController
{
    public function __construct(private ApprovalReminderService $reminders)
    {
    }

    #[Route(
        path: '/api/_action/workflow/instance/{instanceId}/remind',
        name: 'api.workflow.approval.instance.remind',
        defaults: ['auth_required' => true, PlatformRequest::ATTRIBUTE_ACL => ['workflow_instance:start']],
        methods: [Request::METHOD_POST],
    )]
    public function remind(string $instanceId, Context $context): Response
    {
        $this->reminders->remind($instanceId, $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
