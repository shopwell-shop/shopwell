<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Publication;

use Shopwell\Core\Framework\ApprovalFlow\Execution\Executor\NodeExecutorRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Codec\GraphJsonDecoder;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompilerRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Publication\Artifact\ArtifactManifestLoader;
use Shopwell\Core\Framework\Log\Package;

/**
 * @phpstan-import-type NodePlan from NodePlanCompiler
 *
 * @internal
 */
#[Package('framework')]
class ApprovalReleaseCompiler
{
    public function __construct(
        private readonly NodePlanCompiler $nodePlanCompiler,
        private readonly NodeCompilerRegistry $nodeCompilers,
        private readonly NodeExecutorRegistry $nodeExecutors,
        private readonly GraphJsonDecoder $jsonDecoder,
        private readonly ArtifactManifestLoader $artifacts,
    ) {
    }

    public function compile(DraftGraph $draft): CompiledApprovalRelease
    {
        $nodePlan = $this->nodePlanCompiler->compile($draft);
        /** @var NodePlan $plan */
        $plan = $this->jsonDecoder->decode($nodePlan->json);
        $classes = [$this::class, $this->nodePlanCompiler::class, $this->artifacts::class];
        foreach ($plan->nodes as $node) {
            $classes[] = $this->nodeCompilers->get($node->type, $node->typeVersion)::class;
            $classes[] = $this->nodeExecutors->get($node->type, $node->typeVersion)::class;
        }

        return new CompiledApprovalRelease($nodePlan, $this->artifacts->load($classes));
    }
}
