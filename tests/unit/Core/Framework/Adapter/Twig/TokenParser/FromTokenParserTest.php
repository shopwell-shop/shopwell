<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig\TokenParser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\TemplateFinderInterface;
use Shopwell\Core\Framework\Adapter\Twig\TokenParser\FromTokenParser;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FromTokenParser::class)]
class FromTokenParserTest extends TestCase
{
    public function testRenderFromReferencingAnInheritedTemplate(): void
    {
        static::assertSame(
            'stuff from macro',
            $this->parseTemplate('{% sw_from "foo.html.twig" import do_stuff as stuff %}{{ stuff() }}')
        );
    }

    public function testGetTag(): void
    {
        static::assertSame(
            'sw_from',
            (new FromTokenParser(static::createStub(TemplateFinderInterface::class)))->getTag(),
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

        $twig->addTokenParser(new FromTokenParser($templateFinder));

        return $twig->render($templateName);
    }
}
