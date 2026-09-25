<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopwell\Core\Framework\Adapter\Twig\TokenParser\ImportTokenParser;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ImportTokenParser::class)]
class ImportTokenParserTest extends TestCase
{
    public function testRenderImportReferencingAnInheritedTemplate(): void
    {
        static::assertSame(
            'stuff from macro',
            $this->parseTemplate('{% sw_import "foo.html.twig" as stuff %}{{ stuff.do_stuff() }}')
        );
    }

    public function testGetTag(): void
    {
        static::assertSame(
            'sw_import',
            (new ImportTokenParser(static::createStub(TemplateFinderInterface::class)))->getTag(),
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
            'bar.html.twig' => '{% macro do_stuff() %}stuff from macro{% endmacro %}',
        ]));

        $twig->addTokenParser(new ImportTokenParser($templateFinder));

        return $twig->render($templateName);
    }
}
