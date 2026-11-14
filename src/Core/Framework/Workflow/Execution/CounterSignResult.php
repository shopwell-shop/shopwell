<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Workflow\Execution;

use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * 会签聚合的结论。
 *
 * 三个分母数字一起返回，不是为了展示，而是为了让调用方能**断言**：
 * 测试里 `agreed=2, rejected=0, total=3` 能立刻看出分母有没有算错；
 * 只返回一个枚举时，"会签永远差一人"这种 bug 在测试里毫无迹象。
 *
 * `total` 是**计入分母**的任务数（已排除取消/跳过的），不是节点配置的人数。
 * 这两个数在加签、退回场景下会不一样，不要混用。
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
