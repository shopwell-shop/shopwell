<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\ApprovalFlow\Graph\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\ApprovalFlow\ApprovalFlowException;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Loader\NodeTypeLoader;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\NodeEffectType;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Registry\NodeTypeRegistry;
use Shopwell\Core\Framework\ApprovalFlow\Graph\Type\Specification\NodeTypeSpecification;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NodeTypeRegistry::class)]
class NodeTypeRegistryTest extends TestCase
{
    public function testResolvesExactVersionsAcrossIndependentLoaders(): void
    {
        $v1 = $this->specification('1.0.0', 'core');
        $v2 = $this->specification('2.0.0', 'plugin:review');
        $registry = new NodeTypeRegistry([$this->loader($v1), $this->loader($v2)]);

        static::assertSame($v1, $registry->get('review:approval', '1.0.0'));
        static::assertSame($v2, $registry->get('review:approval', '2.0.0'));
        static::assertSame(['review:approval@1.0.0', 'review:approval@2.0.0'], array_keys($registry->all()));
    }

    public function testRejectsDuplicateVersionsWithBothSources(): void
    {
        $registry = new NodeTypeRegistry([
            $this->loader($this->specification('1.0.0', 'core')),
            $this->loader($this->specification('1.0.0', 'plugin:review')),
        ]);

        $this->expectExceptionObject(ApprovalFlowException::duplicateNodeType('review:approval', '1.0.0', 'core', 'plugin:review'));
        $registry->all();
    }

    public function testKeepsOnlyTheCompleteSuccessfulCatalogue(): void
    {
        $specification = $this->specification('1.0.0', 'core');
        $loader = $this->createMock(NodeTypeLoader::class);
        $loader->expects($this->once())->method('load')->willReturn([$specification]);
        $registry = new NodeTypeRegistry([$loader]);

        static::assertSame($specification, $registry->get('review:approval', '1.0.0'));
        static::assertSame(['review:approval@1.0.0' => $specification], $registry->all());
    }

    public function testFailedLoadDoesNotExposeTheFirstOfTwoConflictingDeclarations(): void
    {
        $registry = new NodeTypeRegistry([
            $this->loader($this->specification('1.0.0', 'core')),
            $this->loader($this->specification('1.0.0', 'plugin:review')),
        ]);
        $expected = ApprovalFlowException::duplicateNodeType('review:approval', '1.0.0', 'core', 'plugin:review');
        try {
            $registry->all();
            static::fail('The conflicting catalogue must fail as a whole.');
        } catch (ApprovalFlowException $exception) {
            static::assertEquals($expected, $exception);
        }

        $this->expectExceptionObject($expected);
        $registry->get('review:approval', '1.0.0');
    }

    public function testNeverFallsBackToAnotherVersion(): void
    {
        $registry = new NodeTypeRegistry([$this->loader($this->specification('2.0.0', 'core'))]);

        $this->expectExceptionObject(ApprovalFlowException::nodeTypeNotFound('review:approval', '1.0.0'));
        $registry->get('review:approval', '1.0.0');
    }

    public function testAnEmptyRegistryHasNoCapabilities(): void
    {
        static::assertSame([], (new NodeTypeRegistry([]))->all());
    }

    private function specification(string $version, string $source): NodeTypeSpecification
    {
        return new NodeTypeSpecification(
            'review:approval',
            $version,
            'review.approval',
            $source,
            '{"$id":"review:config","type":"object","additionalProperties":false}',
            ['in' => 'input', 'out' => 'output'],
            [NodeEffectType::Advance],
        );
    }

    private function loader(NodeTypeSpecification $specification): NodeTypeLoader
    {
        $loader = static::createStub(NodeTypeLoader::class);
        $loader->method('load')->willReturn([$specification]);

        return $loader;
    }
}
