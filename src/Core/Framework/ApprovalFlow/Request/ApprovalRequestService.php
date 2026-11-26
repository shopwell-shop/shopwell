<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Request;

use Opis\JsonSchema\Exceptions\SchemaException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Instance\ApprovalInstanceStore;
use Shopwell\Core\Framework\ApprovalFlow\Execution\Operation\ApprovalInstanceCommandResult;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphSchemaValidator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Admits business-generated snapshots; the business owner serializes and freezes its own record.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Request\ApprovalRequestServiceTest
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Request\ApprovalRequestStartTest
 */
#[Package('framework')]
class ApprovalRequestService
{
    public function __construct(
        private readonly ApprovalRequestStore $store,
        private readonly ApprovalBindingService $bindings,
        private readonly GraphSchemaValidator $schemas,
        private readonly ApprovalInstanceStore $instances,
    ) {
    }

    public function create(string $type, string $businessId, int $round, string $businessRevision, string $formJson, Context $context): ApprovalRequestEntity
    {
        $initiatorId = $this->initiatorId($context);

        return $this->store->transactional(function () use ($type, $businessId, $round, $businessRevision, $formJson, $context, $initiatorId): ApprovalRequestEntity {
            $binding = $this->bindings->forSubmission($type, $context);
            try {
                $form = json_decode($formJson, flags: \JSON_THROW_ON_ERROR);
                $violations = $this->schemas->validate($form, $binding->getFormSchema());
            } catch (\JsonException|SchemaException $exception) {
                throw ApprovalFlowException::invalidRequestForm('', 'The form or its schema could not be decoded.', $exception);
            }
            if ($violations !== []) {
                throw ApprovalFlowException::invalidRequestForm($violations[0]->path, $violations[0]->message);
            }

            return $this->store->create($binding, $businessId, $round, $businessRevision, $formJson, $initiatorId, $context);
        });
    }

    public function find(string $type, string $businessId, int $round, Context $context): ?ApprovalRequestEntity
    {
        $initiatorId = $this->initiatorId($context);
        $request = $this->store->find($type, $businessId, $round);
        if ($request !== null && $request->getInitiatorId() !== $initiatorId) {
            throw ApprovalFlowException::requestInitiatorRequired($request->getId());
        }

        return $request;
    }

    public function start(string $requestId, Context $context): ApprovalInstanceCommandResult
    {
        $initiatorId = $this->initiatorId($context);

        return $this->store->transactional(function () use ($requestId, $context, $initiatorId): ApprovalInstanceCommandResult {
            $request = $this->store->read($requestId);
            if ($request->getInitiatorId() !== $initiatorId) {
                throw ApprovalFlowException::requestInitiatorRequired($requestId);
            }
            $actorJson = $this->actorJson($initiatorId);

            // The immutable request identity also identifies its single draft-to-running command.
            return $this->instances->start($request->getInstanceId(), $requestId, expectedVersion: 1, actorJson: $actorJson, context: $context);
        });
    }

    public function withdraw(string $requestId, string $reason, Context $context): void
    {
        $initiatorId = $this->initiatorId($context);
        $this->store->transactional(function () use ($requestId, $reason, $context, $initiatorId): void {
            $request = $this->store->read($requestId);
            if ($request->getInitiatorId() !== $initiatorId) {
                throw ApprovalFlowException::requestInitiatorRequired($requestId);
            }

            $this->instances->withdraw($request->getInstanceId(), $reason, $this->actorJson($initiatorId), $context);
        });
    }

    private function actorJson(string $initiatorId): string
    {
        return json_encode(['source' => 'admin-api', 'userId' => $initiatorId, 'integrationId' => null], \JSON_THROW_ON_ERROR);
    }

    private function initiatorId(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null || $source->getIntegrationId() !== null) {
            throw ApprovalFlowException::requestActorRequired();
        }

        return $source->getUserId();
    }
}
