<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Execution;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\DraftGraph;
use Shopwell\Core\Framework\ApprovalFlow\Publication\ApprovalReleaseCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Publication\CompiledApprovalRelease;
use Shopwell\Core\Framework\ApprovalFlow\Release\ApprovalReleaseService;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

/**
 * Revalidates persisted compilation content against this deployment, without activation authority.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopwell\Tests\Integration\Core\Framework\ApprovalFlow\Execution\ApprovalReleaseLoaderTest
 */
#[Package('framework')]
class ApprovalReleaseLoader
{
    public function __construct(
        private readonly ApprovalReleaseService $releases,
        private readonly ApprovalReleaseCompiler $compiler,
    ) {
    }

    public function load(string $releaseId, Context $context): CompiledApprovalRelease
    {
        $stored = $this->releases->read($releaseId, $context);
        $compiled = $this->compiler->compile(new DraftGraph($stored->getGraphJson()));
        $parts = [
            'graphJson' => [$stored->getGraphJson(), $compiled->graphJson],
            'contractJson' => [$stored->getContractJson(), $compiled->contractJson],
            'graphPlanJson' => [$stored->getGraphPlanJson(), $compiled->graphPlanJson],
            'nodePlanJson' => [$stored->getNodePlanJson(), $compiled->nodePlanJson],
            'dependenciesJson' => [$stored->getDependenciesJson(), $compiled->dependenciesJson],
            'contentHash' => [$stored->getContentHash(), $compiled->hash],
        ];
        foreach ($parts as $part => [$expected, $actual]) {
            if ($expected !== $actual) {
                throw ApprovalFlowException::releaseVerificationFailed($releaseId, $part);
            }
        }

        return $compiled;
    }
}
