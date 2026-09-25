<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\EntityProtection;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
abstract class EntityProtection
{
    /**
     * Returns a readable name for the flag
     *
     * @return \Generator<string>
     */
    abstract public function parse(): \Generator;

    /**
     * Can be overridden if protection is aware of different scopes
     */
    public function isAllowed(string $scope): bool
    {
        return true;
    }
}
