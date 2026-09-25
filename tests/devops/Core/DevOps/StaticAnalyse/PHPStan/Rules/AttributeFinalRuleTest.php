<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\AttributeFinalRule;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends  RuleTestCase<AttributeFinalRule>
 */
#[Package('framework')]
class AttributeFinalRuleTest extends RuleTestCase
{
    public function testFinalAttributeClass(): void
    {
        $this->analyse([
            __DIR__ . '/data/AttributeFinalRule/FinalAttributeClass.php',
        ], []);
    }

    public function testNonFinalAttributeClass(): void
    {
        $this->analyse([
            __DIR__ . '/data/AttributeFinalRule/NonFinalAttributeClass.php',
        ], [[
            'Attribute classes must be declared final.',
            5,
        ]]);
    }

    protected function getRule(): Rule
    {
        return new AttributeFinalRule();
    }
}
