<?php

declare(strict_types=1);

namespace Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class NotExtendFlowEventAwareRule
{
    #[TestRule]
    public function doNotExtendFlowEventAware(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::isInterface())
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname(FlowEventAware::class))
            ->because('Flow events should not be derived from each other to make them easier to test.');
    }
}
