<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Publication;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WorkflowNodeCompilerRegistry
{
    /**
     * @var array<string, WorkflowNodeCompiler>
     */
    private array $compilers;

    /**
     * @param iterable<WorkflowNodeCompiler> $compilers
     */
    public function __construct(iterable $compilers)
    {
        $registered = [];

        foreach ($compilers as $compiler) {
            $key = $compiler->getNodeType() . ':' . $compiler->getNodeVersion();

            if (isset($registered[$key])) {
                throw WorkflowException::publicationRejected('duplicate_node_compiler');
            }

            $registered[$key] = $compiler;
        }

        $this->compilers = $registered;
    }

    public function get(string $name, int $version): WorkflowNodeCompiler
    {
        return $this->compilers[$name . ':' . $version] ?? throw WorkflowException::publicationRejected('node_compiler_unavailable');
    }
}
