<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\Cart\Address\Error;

use Shopwell\Core\Checkout\Cart\Error\Error;
use Shopwell\Core\Framework\Log\Package;

#[Package('checkout')]
abstract class CountryRegionMissingError extends Error implements AddressErrorInterface
{
    protected const KEY = 'country-region-missing';

    /**
     * @var array<string, string>
     */
    protected array $parameters;

    abstract public function getId(): string;

    public function getMessageKey(): string
    {
        return $this->getId();
    }

    public function getLevel(): int
    {
        return Error::LEVEL_WARNING;
    }

    public function blockOrder(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
