<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Binding;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class ApprovalBusinessTypeSourceDirectory
{
    public function __construct(public string $source, public string $path)
    {
    }
}
