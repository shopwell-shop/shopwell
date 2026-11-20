<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Binding\ApprovalBusinessTypeSpecification;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ApprovalBusinessTypeRegistry::class)]
class ApprovalBusinessTypeRegistryTest extends TestCase
{
    public function testResolvesExactBusinessVersionsFromIndependentLoaders(): void
    {
        $v1 = $this->specification(version: '1.0.0', source: 'finance:v1.yaml');
        $v2 = $this->specification(version: '2.0.0', source: 'finance:v2.yaml');
        $registry = new ApprovalBusinessTypeRegistry([$this->loader($v1), $this->loader($v2)]);

        static::assertSame($v1, $registry->get('finance:expense', '1.0.0'));
        static::assertSame($v2, $registry->get('finance:expense', '2.0.0'));
        static::assertSame(['finance:expense@1.0.0' => $v1, 'finance:expense@2.0.0' => $v2], $registry->all());
    }

    public function testDuplicateIdentityFailsEveryReadWithoutPublishingAPartialCatalogue(): void
    {
        $registry = new ApprovalBusinessTypeRegistry([
            $this->loader($this->specification(version: '1.0.0', source: 'finance:first.yaml')),
            $this->loader($this->specification(version: '1.0.0', source: 'plugin:other.yaml')),
        ]);
        $expected = ApprovalFlowException::duplicateBusinessType('finance:expense', '1.0.0', 'finance:first.yaml', 'plugin:other.yaml');
        try {
            $registry->all();
            static::fail('A collision cannot publish either declaration.');
        } catch (ApprovalFlowException $exception) {
            static::assertEquals($expected, $exception);
        }

        $this->expectExceptionObject($expected);
        $registry->get('finance:expense', '1.0.0');
    }

    public function testDoesNotReplaceAMissingVersionWithAnInstalledVersion(): void
    {
        $registry = new ApprovalBusinessTypeRegistry([$this->loader($this->specification(version: '2.0.0', source: 'finance:v2.yaml'))]);
        $this->expectExceptionObject(ApprovalFlowException::businessTypeNotFound('finance:expense', '1.0.0'));

        $registry->get('finance:expense', '1.0.0');
    }

    public function testASuccessfulCatalogueRemainsStableForItsLifetime(): void
    {
        $first = $this->specification(version: '1.0.0', source: 'finance:v1.yaml');
        $changed = $this->specification(version: '2.0.0', source: 'finance:v2.yaml');
        $loader = static::createStub(ApprovalBusinessTypeLoader::class);
        $loader->method('load')->willReturnOnConsecutiveCalls([$first], [$changed]);
        $registry = new ApprovalBusinessTypeRegistry([$loader]);

        static::assertSame(['finance:expense@1.0.0' => $first], $registry->all());
        static::assertSame(['finance:expense@1.0.0' => $first], $registry->all());
    }

    public function testAnEmptyRegistryDoesNotInstallBusinessTypes(): void
    {
        static::assertSame([], (new ApprovalBusinessTypeRegistry([]))->all());
    }

    private function specification(string $version, string $source): ApprovalBusinessTypeSpecification
    {
        return new ApprovalBusinessTypeSpecification(
            'finance:expense',
            $version,
            'finance.approval.expense',
            $source,
            '{"$id":"urn:expense","type":"object"}',
        );
    }

    private function loader(ApprovalBusinessTypeSpecification $specification): ApprovalBusinessTypeLoader
    {
        $loader = static::createStub(ApprovalBusinessTypeLoader::class);
        $loader->method('load')->willReturn([$specification]);

        return $loader;
    }
}
