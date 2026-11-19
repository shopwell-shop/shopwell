<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Compilation;

use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class NodeCompilerRegistry
{
    /**
     * @var array<string, NodeCompiler>|null
     */
    private ?array $compilers = null;

    /**
     * @param iterable<NodeCompiler> $registeredCompilers
     */
    public function __construct(private readonly iterable $registeredCompilers)
    {
    }

    public function get(string $type, string $version): NodeCompiler
    {
        if ($this->compilers === null) {
            $this->compilers = $this->load();
        }

        return $this->compilers[$type . '@' . $version] ?? throw ApprovalFlowException::nodeCompilerNotFound($type, $version);
    }

    /**
     * @return array<string, NodeCompiler>
     */
    private function load(): array
    {
        $compilers = [];
        foreach ($this->registeredCompilers as $compiler) {
            $types = $this->validateTypes($compiler->getTypes(), $compiler::class);
            foreach ($types as $type => $versions) {
                foreach ($versions as $version) {
                    $identity = $type . '@' . $version;
                    if (isset($compilers[$identity])) {
                        throw ApprovalFlowException::duplicateNodeCompiler($type, $version, $compilers[$identity]::class, $compiler::class);
                    }
                    $compilers[$identity] = $compiler;
                }
            }
        }

        return $compilers;
    }

    /**
     * Plugin metadata crosses a runtime boundary even when its PHPDoc promises string keys and version lists.
     *
     * @param array<mixed> $types
     *
     * @return array<string, list<string>>
     */
    private function validateTypes(array $types, string $compiler): array
    {
        if ($types === []) {
            throw ApprovalFlowException::invalidNodeCompiler($compiler, 'A compiler must declare at least one node type.');
        }
        $validated = [];
        foreach ($types as $type => $versions) {
            if (!\is_string($type) || !preg_match('/^[a-z][a-z0-9_-]*:[a-z][a-zA-Z0-9_-]*$/D', $type)) {
                throw ApprovalFlowException::invalidNodeCompiler($compiler, 'Types must be namespaced identifiers.');
            }
            if (!\is_array($versions) || !array_is_list($versions) || $versions === []) {
                throw ApprovalFlowException::invalidNodeCompiler($compiler, 'A node type must declare a nonempty list of exact versions.');
            }
            $validatedVersions = [];
            foreach ($versions as $version) {
                if (!\is_string($version) || !preg_match('/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $version)) {
                    throw ApprovalFlowException::invalidNodeCompiler($compiler, 'Versions must use exact major.minor.patch values.');
                }
                $validatedVersions[] = $version;
            }
            $validated[$type] = $validatedVersions;
        }

        return $validated;
    }
}
