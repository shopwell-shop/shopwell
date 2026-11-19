<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class NodeTypeSourceDirectory
{
    public function __construct(public string $source, public string $path)
    {
    }
}
