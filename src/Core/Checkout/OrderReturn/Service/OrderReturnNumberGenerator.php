<?php declare(strict_types=1);

namespace Shopwell\Core\Checkout\OrderReturn\Service;

use Psr\Clock\ClockInterface;
use Shopwell\Core\Framework\Log\Package;

/**
 * The number a return is quoted by.
 *
 * It is built and not drawn from a number range: a return number is only ever shown to a customer
 * and a support agent, never used for accounting, and a range would make the migration of every
 * installation depend on a range row that a plugin could have changed. The format follows the one of
 * a payment refund, so both kinds of documents read the same way.
 *
 * @final
 */
#[Package('after-sales')]
class OrderReturnNumberGenerator
{
    private const PREFIX = 'RT';

    /**
     * @internal
     */
    public function __construct(private readonly ClockInterface $clock)
    {
    }

    public function generate(): string
    {
        return self::PREFIX
            . $this->clock->now()->format('YmdHis')
            . strtoupper(bin2hex(random_bytes(3)));
    }
}
