<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Draft;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphCodec;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutationResult;
use Shopwell\Core\Framework\ApprovalFlow\Mutation\GraphMutator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Draft\ApprovalDraftServiceTest
 */
#[Package('framework')]
class ApprovalDraftService
{
    public function __construct(
        private readonly ApprovalDraftStore $store,
        private readonly GraphCodec $codec,
        private readonly GraphMutator $mutator,
    ) {
    }

    public function create(string $name, string $graphJson, Context $context): ApprovalDraft
    {
        $write = $this->codec->prepareDraft($graphJson);
        $draft = new ApprovalDraft(Uuid::randomHex(), 1, $write['graph'], $write['diagnostics']);

        $this->store->create($draft->definitionId, $name, $draft->graph->json, $context);

        return $draft;
    }

    public function read(string $definitionId, Context $context): ApprovalDraft
    {
        $stored = $this->store->read($definitionId, $context);
        $read = $this->codec->readDraft($stored->getGraphJson());

        return new ApprovalDraft($stored->getDefinitionId(), $stored->getRevision(), $read['graph'], $read['diagnostics']);
    }

    public function addNode(string $definitionId, int $expectedRevision, string $nodeJson, Context $context): ApprovalDraftEdit
    {
        return $this->edit($definitionId, $expectedRevision, $context, fn (DraftGraph $graph) => $this->mutator->addNode($graph, $nodeJson));
    }

    public function addConnection(string $definitionId, int $expectedRevision, string $connectionJson, Context $context): ApprovalDraftEdit
    {
        return $this->edit($definitionId, $expectedRevision, $context, fn (DraftGraph $graph) => $this->mutator->addConnection($graph, $connectionJson));
    }

    public function removeNode(string $definitionId, int $expectedRevision, string $nodeId, bool $confirmRemoval, Context $context): ApprovalDraftEdit
    {
        return $this->edit($definitionId, $expectedRevision, $context, fn (DraftGraph $graph) => $this->mutator->removeNode($graph, $nodeId), $confirmRemoval);
    }

    public function removeConnection(string $definitionId, int $expectedRevision, string $connectionId, bool $confirmRemoval, Context $context): ApprovalDraftEdit
    {
        return $this->edit($definitionId, $expectedRevision, $context, fn (DraftGraph $graph) => $this->mutator->removeConnection($graph, $connectionId), $confirmRemoval);
    }

    /**
     * @param \Closure(DraftGraph): GraphMutationResult $mutate
     */
    private function edit(
        string $definitionId,
        int $expectedRevision,
        Context $context,
        \Closure $mutate,
        bool $confirmRemoval = false,
    ): ApprovalDraftEdit {
        return $this->store->update($definitionId, $expectedRevision, $context, static function (string $graphJson, int $revision) use ($mutate, $confirmRemoval): array {
            $mutation = $mutate(new DraftGraph($graphJson));

            if (!$confirmRemoval && ($mutation->removedNodes !== [] || $mutation->removedConnections !== [] || $mutation->removedLayout !== [])) {
                throw ApprovalFlowException::removalNotConfirmed();
            }

            return [
                'graphJson' => $mutation->graph->json,
                'result' => new ApprovalDraftEdit($revision, $mutation),
            ];
        });
    }
}
