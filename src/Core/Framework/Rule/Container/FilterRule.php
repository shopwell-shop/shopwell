<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Rule\Container;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Rule;

#[Package('fundamentals@after-sales')]
abstract class FilterRule extends Rule implements ContainerInterface
{
    protected ?Container $filter = null;

    public function addRule(Rule $rule): void
    {
        if ($this->filter === null) {
            $this->filter = new AndRule();
        }

        $this->filter->addRule($rule);
    }

    /**
     * @param list<Rule> $rules
     */
    public function setRules(array $rules): void
    {
        $this->filter = new AndRule($rules);
    }

    /**
     * @return list<Rule>
     */
    public function getRules(): array
    {
        return $this->filter ? $this->filter->getRules() : [];
    }
}
