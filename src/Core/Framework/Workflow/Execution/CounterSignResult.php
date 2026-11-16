<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * Return the decision and counts for tasks included in the current aggregation.
 *
 * @internal
 */
#[Package('framework')]
final class CounterSignResult
{
    public const AGREED = 'agreed';
    public const REJECTED = 'rejected';
    public const PENDING = 'pending';

    private function __construct(
        private readonly string $status,
        private readonly int $agreed,
        private readonly int $rejected,
        private readonly int $total,
    ) {
    }

    public static function agreed(int $agreed, int $rejected, int $total): self
    {
        return new self(self::AGREED, $agreed, $rejected, $total);
    }

    public static function rejected(int $agreed, int $rejected, int $total): self
    {
        return new self(self::REJECTED, $agreed, $rejected, $total);
    }

    public static function pending(int $agreed, int $rejected, int $total): self
    {
        return new self(self::PENDING, $agreed, $rejected, $total);
    }

    public function isAgreed(): bool
    {
        return $this->status === self::AGREED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::REJECTED;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function agreedCount(): int
    {
        return $this->agreed;
    }

    public function rejectedCount(): int
    {
        return $this->rejected;
    }

    public function total(): int
    {
        return $this->total;
    }
}
