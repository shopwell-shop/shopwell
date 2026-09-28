<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\NoStripTagsRule;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<NoStripTagsRule>
 */
#[Package('framework')]
class NoStripTagsRuleTest extends RuleTestCase
{
    public function testRuleReportsStripTagsCalls(): void
    {
        $class = 'Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NoStripTagsRule\HasStripTags';

        $this->analyse([__DIR__ . '/data/NoStripTagsRule/HasStripTags.php'], [
            [\sprintf(NoStripTagsRule::ERROR_MESSAGE, $class), 9],
            [\sprintf(NoStripTagsRule::ERROR_MESSAGE, $class), 10],
        ]);
    }

    public function testOtherFunctionsAndMethodsOfTheSameNameAreNotReported(): void
    {
        $this->analyse([__DIR__ . '/data/NoStripTagsRule/HasNoStripTags.php'], []);
    }

    public function testAllowlistedClassesAreNotReported(): void
    {
        $this->analyse([
            __DIR__ . '/../../../../../../../src/Elasticsearch/Framework/ElasticsearchIndexingUtils.php',
        ], []);
    }

    /**
     * @return NoStripTagsRule
     */
    protected function getRule(): Rule
    {
        return new NoStripTagsRule($this->createReflectionProvider());
    }
}
