<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Publication\_fixtures;

use Shopwell\Core\Framework\ApprovalFlow\Node\Compilation\NodeCompiler;
use Shopwell\Core\Framework\ApprovalFlow\Node\Configuration\NodeConfiguration;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class FinanceNodeCompiler implements NodeCompiler
{
    public function getTypes(): array
    {
        return ['finance:review' => ['1.0.0']];
    }

    public function compile(string $type, string $version, string $configurationJson): NodeConfiguration
    {
        $data = json_decode($configurationJson, associative: true, flags: \JSON_THROW_ON_ERROR);
        if (!\is_array($data) || !\is_int($data['limit'] ?? null) || !\is_bool($data['enabled'] ?? null)) {
            throw new \RuntimeException('Invalid finance fixture configuration.');
        }

        return new FinanceNodeConfiguration($data['limit'], $data['enabled']);
    }
}
