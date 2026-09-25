<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\SystemCheck;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\SystemCheck\Check\Category;
use Shopwell\Core\Framework\SystemCheck\Check\Result;
use Shopwell\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

#[Package('framework')]
abstract class BaseCheck
{
    abstract public function run(): Result;

    abstract public function category(): Category;

    abstract public function name(): string;

    public function allowedToRunIn(SystemCheckExecutionContext $context): bool
    {
        return \in_array($context, $this->allowedSystemCheckExecutionContexts(), true);
    }

    /**
     * @return array<SystemCheckExecutionContext>
     */
    abstract protected function allowedSystemCheckExecutionContexts(): array;
}
