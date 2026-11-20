<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Request;

use Opis\JsonSchema\Exceptions\SchemaException;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBindingService;
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
 */
#[Package('framework')]
class ApprovalRequestService
{
    public function __construct(
        private readonly ApprovalRequestStore $store,
        private readonly ApprovalBindingService $bindings,
        private readonly GraphSchemaValidator $schemas,
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

    private function initiatorId(Context $context): string
    {
        $source = $context->getSource();
        if (!$source instanceof AdminApiSource || $source->getUserId() === null || $source->getIntegrationId() !== null) {
            throw ApprovalFlowException::requestActorRequired();
        }

        return $source->getUserId();
    }
}
