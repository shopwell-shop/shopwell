<?php declare(strict_types=1);

namespace Shopwell\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestReflectionClassInterface;
use Shopwell\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class TestRuleHelperTest extends TestCase
{
    #[DataProvider('classProvider')]
    public function testIsTestClass(string $className, bool $extendsTestCase, bool $isTestClass, bool $isUnitTestClass): void
    {
        $classReflection = static::createStub(TestReflectionClassInterface::class);
        $classReflection
            ->method('getName')
            ->willReturn($className);

        if ($extendsTestCase) {
            $parentClass = static::createStub(TestReflectionClassInterface::class);
            $parentClass
                ->method('getName')
                ->willReturn(TestCase::class);

            $classReflection
                ->method('getParents')
                ->willReturn([$parentClass]);
        }

        static::assertSame($isTestClass, TestRuleHelper::isTestClass($classReflection));
        static::assertSame($isUnitTestClass, TestRuleHelper::isUnitTestClass($classReflection));
    }

    public function testIsUnitTestClassUsesConfiguredNamespaces(): void
    {
        $classReflection = $this->createTestClassReflection('Shopwell\Commercial\Tests\Unit\SomeTestClass');

        static::assertFalse(TestRuleHelper::isUnitTestClass($classReflection));
        static::assertTrue(TestRuleHelper::isUnitTestClass($classReflection, ['Shopwell\\Commercial\\Tests\\Unit\\']));
    }

    public static function classProvider(): \Generator
    {
        yield [
            'className' => 'Shopwell\Some\NonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopwell\Commercial\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopwell\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopwell\Tests\Unit\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => true,
        ];

        yield [
            'className' => 'Shopwell\Tests\Integration\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopwell\Tests\SomeNonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];
    }

    private function createTestClassReflection(string $className): TestReflectionClassInterface
    {
        $classReflection = static::createStub(TestReflectionClassInterface::class);
        $classReflection
            ->method('getName')
            ->willReturn($className);

        $parentClass = static::createStub(TestReflectionClassInterface::class);
        $parentClass
            ->method('getName')
            ->willReturn(TestCase::class);

        $classReflection
            ->method('getParents')
            ->willReturn([$parentClass]);

        return $classReflection;
    }
}
