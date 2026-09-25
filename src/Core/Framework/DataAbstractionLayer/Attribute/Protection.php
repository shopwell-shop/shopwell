<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Attribute;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Protection
{
    final public const SYSTEM_SCOPE = Context::SYSTEM_SCOPE;
    final public const USER_SCOPE = Context::USER_SCOPE;
    final public const CRUD_API_SCOPE = Context::CRUD_API_SCOPE;

    /**
     * @param array<string> $write
     */
    public function __construct(public array $write)
    {
    }
}
