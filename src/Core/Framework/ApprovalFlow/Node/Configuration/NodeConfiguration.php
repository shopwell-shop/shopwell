<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Node\Configuration;

use Shopwell\Core\Framework\Log\Package;

/**
 * Node-specific derived configuration; its JSON is frozen into a plan, not used as publication authority.
 */
#[Package('framework')]
interface NodeConfiguration extends \JsonSerializable
{
    /**
     * @return array<string, mixed>|\stdClass An object whose fields belong to the node compiler.
     */
    public function jsonSerialize(): array|\stdClass;
}
