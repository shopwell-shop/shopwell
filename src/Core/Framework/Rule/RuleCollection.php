<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Rule;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Container\Container;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Rule>
 */
#[Package('fundamentals@after-sales')]
class RuleCollection extends Collection
{
    /**
     * @var Rule[]
     */
    protected array $flat = [];

    /**
     * @var bool[]
     */
    protected array $classes = [];

    public function add($rule): void
    {
        parent::add($rule);

        $this->addMeta($rule);
    }

    public function set($key, $rule): void
    {
        parent::set(null, $rule);

        $this->addMeta($rule);
    }

    public function clear(): void
    {
        parent::clear();

        $this->flat = [];
        $this->classes = [];
    }

    public function filterInstance(string $class): static
    {
        return new static(
            array_filter(
                $this->flat,
                static fn (Rule $rule) => $rule instanceof $class
            )
        );
    }

    /**
     * @param class-string $class
     */
    public function has($class): bool
    {
        return \array_key_exists($class, $this->classes);
    }

    public function getApiAlias(): string
    {
        return 'dal_rule_collection';
    }

    private function addMeta(Rule $rule): void
    {
        $this->classes[$rule::class] = true;

        $this->flat[] = $rule;

        if ($rule instanceof Container) {
            foreach ($rule->getRules() as $childRule) {
                $this->addMeta($childRule);
            }
        }
    }
}
