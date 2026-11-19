<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Compilation;

use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * Compiles configuration only, without resolving users, reading business state or executing a node.
 * Each implementation validates its complete configuration schema and owns its semantic rules.
 */
#[Package('framework')]
interface NodeCompiler
{
    /**
     * @return array<string, list<string>> Node types mapped to their exact supported versions.
     */
    public function getTypes(): array;

    /**
     * Shape and semantic failures use the owning module's HttpException with status 400 or 422.
     * An optional path parameter is a configuration-local JSON pointer; the host supplies the node prefix.
     * Other failures propagate unchanged. A compiler must not resolve permissions or perform external work.
     */
    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration;
}
