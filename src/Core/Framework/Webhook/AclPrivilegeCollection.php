<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook;

use Shopwell\Core\Framework\Log\Package;

/**
 * @final
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AclPrivilegeCollection
{
    /**
     * @param array<string> $privileges
     */
    public function __construct(private readonly array $privileges)
    {
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        return \in_array($resource . ':' . $privilege, $this->privileges, true);
    }
}
