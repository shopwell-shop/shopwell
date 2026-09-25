<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\ClassAliasMap;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\NoClassAliasUsageRule;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage;

/**
 * @internal
 *
 * @extends RuleTestCase<NoClassAliasUsageRule>
 */
#[Package('framework')]
class NoClassAliasUsageRuleTest extends RuleTestCase
{
    public function testOldClassNameUsagesAreReported(): void
    {
        $message = 'Class alias "Shopwell\Tests\Legacy\UnregisteredClass" is kept only for backwards compatibility. Use "Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\BCChangeAttributeUsageRule\ClassMovedAttributeUsage" instead.';

        $this->analyse([__DIR__ . '/data/NoClassAliasUsageRule/ClassAliasUsage.php'], [
            [$message, 10],
            [$message, 14],
            [$message, 14],
            [$message, 17],
        ]);
    }

    protected function getRule(): Rule
    {
        return new NoClassAliasUsageRule(new ClassAliasMap([
            'Shopwell\Tests\Legacy\UnregisteredClass' => ClassMovedAttributeUsage::class,
        ]));
    }
}
