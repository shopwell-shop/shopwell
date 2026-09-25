<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopwell\Core\Framework\Adapter\Twig\TokenParser\UseTokenParser;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UseTokenParser::class)]
class UseTokenParserTest extends TestCase
{
    public function testRenderFromReferencingAnInheritedTemplate(): void
    {
        static::assertSame(
            'foobar from block',
            $this->parseTemplate('{% sw_use "foo.html.twig" %}{{ block("foobar") }}')
        );
    }

    public function testGetTag(): void
    {
        static::assertSame(
            'sw_use',
            (new UseTokenParser(static::createStub(TemplateFinderInterface::class)))->getTag(),
        );
    }

    private function parseTemplate(string $template): string
    {
        $templateName = Uuid::randomHex() . '.html.twig';
        $templateFinder = $this->createMock(TemplateFinderInterface::class);
        $templateFinder->expects($this->once())
            ->method('find')
            ->with('foo.html.twig', false, null)
            ->willReturn('bar.html.twig');

        $twig = new Environment(new ArrayLoader([
            $templateName => $template,
            'bar.html.twig' => '{% block foobar %}foobar from block{% endblock %}',
        ]));

        $twig->addTokenParser(new UseTokenParser($templateFinder));

        return $twig->render($templateName);
    }
}
