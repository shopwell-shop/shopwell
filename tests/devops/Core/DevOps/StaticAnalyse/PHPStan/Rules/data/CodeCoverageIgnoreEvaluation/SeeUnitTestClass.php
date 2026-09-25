<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\CodeCoverageIgnoreEvaluation;

use Shopwell\Tests\Unit\Core\Framework\SomeUnitTest;

/**
 * @codeCoverageIgnore
 *
 * @see SomeUnitTest
 */
class SeeUnitTestClass
{
    public function describe(int $age): string
    {
        if ($age >= 18) {
            return 'adult';
        }

        return 'minor';
    }
}
