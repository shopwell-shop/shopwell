<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\ShopwellNamespaceStyleRule;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends RuleTestCase<ShopwellNamespaceStyleRule>
 */
#[Package('framework')]
class ShopwellNamespaceStyleRuleTest extends RuleTestCase
{
    #[RunInSeparateProcess]
    public function testRule(): void
    {
        $this->analyse([__DIR__ . '/data/NamespaceStyle/AllCorrect.php'], []);

        $this->analyse([__DIR__ . '/data/NamespaceStyle/NoShopwell.php'], [
            [
                'Namespace must start with Shopwell',
                3,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/NamespaceStyle/GlobalCommand.php'], [
            [
                'No global Command directories allowed, put your commands in the right domain directory',
                3,
            ],
        ]);

        $this->analyse([__DIR__ . '/data/NamespaceStyle/GlobalException.php'], [
            [
                'No global Exception directories allowed, put your exceptions in the right domain directory',
                3,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new ShopwellNamespaceStyleRule();
    }
}
