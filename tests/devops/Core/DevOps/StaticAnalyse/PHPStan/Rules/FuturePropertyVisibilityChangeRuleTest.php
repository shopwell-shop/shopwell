<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation\FuturePropertyVisibilityChangeRule;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<FuturePropertyVisibilityChangeRule>
 */
#[Package('framework')]
class FuturePropertyVisibilityChangeRuleTest extends RuleTestCase
{
    public function testPropertyAccessOutsideFutureVisibilityScopeIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/FuturePropertyVisibilityChangeRule/property-access.php'], [
            [
                'Property "Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyVisibilityChangeRule\FutureProtectedProperty::$value" will become protected in v6.8.0. This access will break; stop accessing it from outside that scope.',
                7,
            ],
            [
                'Property "Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\FuturePropertyVisibilityChangeRule\FuturePrivateProperty::$value" will become private in v6.8.0. This access will break; stop accessing it from outside that scope.',
                7,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new FuturePropertyVisibilityChangeRule($this->createReflectionProvider());
    }
}
