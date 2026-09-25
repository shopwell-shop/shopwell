<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\Acl\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\NestedEvent;
use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class AclGetAdditionalPrivilegesEvent extends NestedEvent
{
    /**
     * @param array<string> $privileges
     */
    public function __construct(
        private readonly Context $context,
        private array $privileges
    ) {
    }

    /**
     * @return array<string>
     */
    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    /**
     * @param array<string> $privileges
     */
    public function setPrivileges(array $privileges): void
    {
        $this->privileges = $privileges;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
