<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval\Mode;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Graph\GraphNode;
use Shopwell\Core\Framework\Workflow\Execution\WorkflowExecutionException;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @final
 */
#[Package('framework')]
class ApprovalModeRegistry
{
    /**
     * @var array<string, array<int, ApprovalMode>>
     */
    private array $names = [];

    /**
     * @var array<string, array<int, ApprovalMode>>
     */
    private array $types = [];

    /**
     * @internal
     *
     * @param iterable<ApprovalMode> $modes
     */
    public function __construct(iterable $modes)
    {
        foreach ($modes as $mode) {
            $name = $mode->getName();
            $type = $mode->getType();
            $version = $mode->getVersion();

            if (!preg_match('/^[a-z][a-z0-9-]*:[a-z][a-z0-9-]*$/D', $name)
                || !preg_match('/^[a-zA-Z][a-zA-Z0-9:-]{0,31}$/D', $type)
                || $version < 1) {
                throw WorkflowException::invalidDocument('/approval/mode', 'Approval mode identifiers or version are invalid.');
            }

            if (isset($this->names[$name][$version]) || isset($this->types[$type][$version])) {
                throw WorkflowExecutionException::duplicateRegistration('approval mode', $name . '@' . $version);
            }

            $this->names[$name][$version] = $mode;
            $this->types[$type][$version] = $mode;
        }
    }

    public function get(string $name, int $version = 1): ApprovalMode
    {
        return $this->names[$name][$version] ?? throw WorkflowException::approvalModeUnavailable($name, $version);
    }

    public function forType(string $type, int $version = 1): ApprovalMode
    {
        return $this->types[$type][$version] ?? throw WorkflowException::approvalModeUnavailable($type, $version);
    }

    public function has(string $type, int $version = 1): bool
    {
        return isset($this->types[$type][$version]);
    }

    /**
     * @internal
     */
    public function forNode(GraphNode $node): ApprovalMode
    {
        $type = $node->property('countersignType');
        $version = $node->property('approvalModeVersion', 1);

        if (!\is_string($type) || !\is_int($version)) {
            throw WorkflowException::invalidDocument('/approval/mode', 'Approval mode identifier and version are required.');
        }

        $mode = $this->forType($type, $version);
        $mode->validateConfiguration($this->configuration($node));

        return $mode;
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    public function configuration(GraphNode $node): array
    {
        $configuration = $node->property('approvalModeConfig', []);

        if (!\is_array($configuration) || ($configuration !== [] && array_is_list($configuration))) {
            throw WorkflowException::invalidDocument('/approval/config', 'Approval mode configuration must be an object.');
        }

        return $configuration;
    }
}
