<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Configuration;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\NoCreateMockWithoutExpectationsRule;
use Shopwell\Core\Framework\Log\Package;

// the abstract-base fixtures are not autoloadable (their namespace deliberately sits in the rule's
// enabled namespaces); loading them lets reflection resolve the subclass -> ancestor walk
require_once __DIR__ . '/data/NoCreateMockWithoutExpectationsRule/AbstractBaseCases.php';

/**
 * @internal
 *
 * @extends RuleTestCase<NoCreateMockWithoutExpectationsRule>
 */
#[Package('framework')]
class NoCreateMockWithoutExpectationsRuleNarrowingTest extends RuleTestCase
{
    public function testEnabledNamespacesSilenceTheOtherTrees(): void
    {
        // the fixtures live under Shopwell\Tests\Unit\Core; narrowing enforcement to a commercial
        // namespace must silence them
        $this->analyse([__DIR__ . '/data/NoCreateMockWithoutExpectationsRule/Cases.php'], []);
    }

    protected function getRule(): Rule
    {
        return new NoCreateMockWithoutExpectationsRule(
            new Configuration(['createMockWithoutExpectationsEnabledNamespaces' => ['Shopwell\\Commercial\\Tests\\Unit\\Sso\\']]),
            self::getContainer()->getService('defaultAnalysisParser'),
        );
    }
}
