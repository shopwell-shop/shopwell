<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Diagnostics\GraphDiagnostics;
use Shopwell\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\CoreNodeType;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controls which immutable flow release a business type selects for future submissions.
 *
 * @phpstan-import-type GraphPayload from GraphDiagnostics
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Binding\ApprovalBindingServiceTest
 */
#[Package('framework')]
class ApprovalBindingService
{
    public function __construct(
        private readonly ApprovalBindingStore $store,
        private readonly ApprovalBusinessTypeRegistry $businessTypes,
        private readonly ApprovalReleaseLoader $releases,
        private readonly EventDispatcherInterface $events,
    ) {
    }

    public function save(string $type, string $businessVersion, string $releaseId, int $expectedRevision, Context $context): ApprovalBindingEntity
    {
        $businessType = $this->businessTypes->get($type, $businessVersion);

        return $this->store->save($businessType, $releaseId, $expectedRevision, $context, function () use ($businessType, $releaseId, $context): void {
            $this->verifyRelease($businessType, $releaseId, $context);
        });
    }

    public function activate(string $type, int $revision, int $expectedVersion, Context $context): ApprovalBindingHeadEntity
    {
        return $this->store->activate($type, $revision, $expectedVersion, $context, function (string $businessVersion, string $formSchema, string $releaseId) use ($type, $context): void {
            $businessType = $this->businessTypes->get($type, $businessVersion);
            if ($formSchema !== $businessType->formSchema) {
                throw ApprovalFlowException::bindingFormChanged($type, $businessVersion);
            }
            $this->verifyRelease($businessType, $releaseId, $context);
        });
    }

    public function activateRelease(string $type, string $businessVersion, string $releaseId, int $expectedRevision, int $expectedVersion, Context $context): ApprovalBindingHeadEntity
    {
        $businessType = $this->businessTypes->get($type, $businessVersion);

        return $this->store->activateRelease(
            $businessType,
            $releaseId,
            $expectedRevision,
            $expectedVersion,
            $context,
            fn () => $this->verifyRelease($businessType, $releaseId, $context),
        );
    }

    public function forSubmission(string $type, Context $context): ApprovalBindingEntity
    {
        $binding = $this->store->activeForSubmission($type);
        $businessType = $this->businessTypes->get($type, $binding->getBusinessVersion());
        if ($binding->getFormSchema() !== $businessType->formSchema) {
            throw ApprovalFlowException::bindingFormChanged($type, $businessType->version);
        }
        $this->verifyRelease($businessType, $binding->getReleaseId(), $context);

        return $binding;
    }

    private function verifyRelease(ApprovalBusinessTypeSpecification $businessType, string $releaseId, Context $context): void
    {
        $release = $this->releases->load($releaseId);
        // The release loader has recompiled and verified the complete graph before these fields are used.
        /** @var GraphPayload $graph */
        $graph = json_decode($release->graphJson, flags: \JSON_THROW_ON_ERROR);
        foreach ($graph->nodes as $node) {
            if ($node->type === CoreNodeType::START && $node->config->businessType !== $businessType->type) {
                throw ApprovalFlowException::bindingReleaseMismatch($businessType->type, 'businessType');
            }
        }
        if ($graph->meta->formSchemaRef !== $businessType->formSchemaId) {
            throw ApprovalFlowException::bindingReleaseMismatch($businessType->type, 'formSchemaRef');
        }

        $this->events->dispatch(new ApprovalBindingValidationEvent($businessType, $release->graphJson, $context));
    }
}
