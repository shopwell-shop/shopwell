<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\TemplateIterator;
use Shopwell\Core\Framework\Bundle;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Bundle\TwigBundle\TemplateIterator as SymfonyTemplateIterator;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateIterator::class)]
class TemplateIteratorTest extends TestCase
{
    private const FIXTURE_BUNDLE_NAME = 'TemplateIteratorShopwellFixture';

    private TemplateIterator $iterator;

    protected function setUp(): void
    {
        $fixtureBundlePath = __DIR__ . '/_fixtures/template-iterator/TemplateIteratorShopwellFixtureBundle';
        $fixtureBundle = new TemplateIteratorShopwellFixtureBundle($fixtureBundlePath);

        $kernel = static::createStub(KernelInterface::class);
        $kernel
            ->method('getBundles')
            ->willReturn([self::FIXTURE_BUNDLE_NAME => $fixtureBundle]);

        $this->iterator = new TemplateIterator(
            new SymfonyTemplateIterator($kernel, namePatterns: ['*.twig']),
            [self::FIXTURE_BUNDLE_NAME => TemplateIteratorShopwellFixtureBundle::class],
            [self::FIXTURE_BUNDLE_NAME => ['path' => $fixtureBundlePath]],
        );
    }

    public function testIteratorStripsShopwellBundleNamespacePrefix(): void
    {
        $templateList = iterator_to_array($this->iterator, false);

        static::assertContains('files/agentic/llms.txt.twig', $templateList);

        foreach ($templateList as $template) {
            static::assertStringNotContainsString('@' . self::FIXTURE_BUNDLE_NAME . '/', $template);
        }
    }

    public function testIteratorKeepsSymfonyDefaultDotFileBehavior(): void
    {
        $templateList = iterator_to_array($this->iterator, false);

        static::assertContains('files/agentic/llms.txt.twig', $templateList);

        foreach ($templateList as $template) {
            static::assertStringNotContainsString('/.', $template);
        }
    }

    public function testFilteredLookupIncludesHiddenTemplatePathsWhenRequested(): void
    {
        $templateList = iterator_to_array($this->iterator->getTemplatePathsForSubPath('files/agentic', true), false);
        sort($templateList);

        static::assertSame([
            'files/agentic/.well-known/ucp.json.twig',
            'files/agentic/llms.txt.twig',
        ], $templateList);
    }

    public function testFilteredLookupCanKeepDefaultDotFileBehavior(): void
    {
        $templateList = iterator_to_array($this->iterator->getTemplatePathsForSubPath('files/agentic'), false);

        static::assertSame(['files/agentic/llms.txt.twig'], $templateList);
    }
}

/**
 * @internal
 */
final class TemplateIteratorShopwellFixtureBundle extends Bundle
{
    public function __construct(private readonly string $fixturePath)
    {
    }

    public function getPath(): string
    {
        return $this->fixturePath;
    }
}
