<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\ApprovalFlow\Graph\Type;

use Shopwell\Core\Framework\Log\Package;

/**
 * Built-in identities only; the node catalogue remains open to namespaced plugin identifiers.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CoreNodeType
{
    public const START = 'core:start';

    public const APPROVAL = 'core:approval';

    public const END = 'core:end';
}
