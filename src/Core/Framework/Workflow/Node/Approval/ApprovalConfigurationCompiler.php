<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Node\Approval;

use Shopwell\Core\Framework\Extensions\ExtensionDispatcher;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowViolation;
use Shopwell\Core\Framework\Workflow\Node\Approval\Assignee\ApprovalAssigneeRegistry;
use Shopwell\Core\Framework\Workflow\Node\Approval\Extension\CompileApprovalConfigurationExtension;
use Shopwell\Core\Framework\Workflow\Node\Approval\Mode\ApprovalModeRegistry;
use Shopwell\Core\Framework\Workflow\WorkflowException;

/**
 * @internal
 */
#[Package('framework')]
final readonly class ApprovalConfigurationCompiler
{
    public function __construct(
        private ApprovalAssigneeRegistry $sources,
        private ApprovalModeRegistry $modes,
        private ExtensionDispatcher $extensions,
    ) {
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function compile(array $configuration, string $path, string $nodeId): CompiledApprovalConfiguration
    {
        $compiled = $this->extensions->publish(
            CompileApprovalConfigurationExtension::NAME,
            new CompileApprovalConfigurationExtension($configuration, $path, $nodeId),
            $this->compileConfiguration(...)
        );

        // Replacement results must use a registered, versioned implementation as well.
        $installed = $this->modes->forType($compiled->mode->getType(), $compiled->mode->getVersion());

        if ($installed !== $compiled->mode) {
            throw $this->rejected('approval_mode_unavailable', $path . '/approval/mode', $nodeId);
        }

        $installed->validateConfiguration($compiled->configuration);

        if ($compiled->assignment->multiple !== $installed->supportsMultipleAssignees()) {
            throw $this->rejected('unsupported_approval_assignment', $path . '/assignee', $nodeId);
        }

        return $compiled;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function compileConfiguration(array $configuration, string $path, string $nodeId): CompiledApprovalConfiguration
    {
        $assignee = $this->metadata($configuration['assignee'] ?? null, ['source', 'config'], $path . '/assignee', $nodeId);
        $approval = $this->metadata($configuration['approval'] ?? null, ['mode', 'config', 'version'], $path . '/approval', $nodeId);
        $sourceConfig = $this->object($assignee['config'] ?? null, $path . '/assignee/config', $nodeId);
        $modeConfig = $this->object($approval['config'] ?? null, $path . '/approval/config', $nodeId);
        $version = \array_key_exists('version', $approval) ? $approval['version'] : 1;

        if (!\is_string($assignee['source'] ?? null) || !\is_string($approval['mode'] ?? null) || !\is_int($version) || $version < 1) {
            throw $this->rejected('approval_invalid_configuration', $path, $nodeId);
        }

        try {
            $source = $this->sources->get($assignee['source']);
        } catch (WorkflowException) {
            throw $this->rejected('unsupported_approval_assignment', $path . '/assignee/source', $nodeId);
        }

        try {
            $assignment = $source->compile($sourceConfig);
        } catch (WorkflowException $exception) {
            throw $this->rejected('invalid_reviewer', $path . '/assignee/config' . ($exception->getParameters()['path'] ?? ''), $nodeId);
        }

        try {
            $mode = $this->modes->get($approval['mode'], $version);
        } catch (WorkflowException) {
            throw $this->rejected('approval_mode_unavailable', $path . '/approval/mode', $nodeId);
        }

        try {
            $mode->validateConfiguration($modeConfig);
        } catch (WorkflowException) {
            throw $this->rejected('invalid_approval_configuration', $path . '/approval/config', $nodeId);
        }

        return new CompiledApprovalConfiguration($assignment, $mode, $modeConfig);
    }

    /**
     * @param list<string> $allowedKeys
     *
     * @return array<string, mixed>
     */
    private function metadata(mixed $value, array $allowedKeys, string $path, string $nodeId): array
    {
        $metadata = $this->object($value, $path, $nodeId);

        if (array_diff(array_keys($metadata), $allowedKeys) !== []) {
            throw $this->rejected('approval_invalid_configuration', $path, $nodeId);
        }

        return $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $path, string $nodeId): array
    {
        if ($value instanceof \stdClass) {
            $value = get_object_vars($value);
        }

        if (!\is_array($value) || ($value !== [] && array_is_list($value))) {
            throw $this->rejected('approval_invalid_configuration', $path, $nodeId);
        }

        return $value;
    }

    private function rejected(string $code, string $path, string $nodeId): WorkflowException
    {
        return WorkflowException::publicationRejected('unsupported_approval_workflow', [new WorkflowViolation($code, $path, $nodeId)]);
    }
}
