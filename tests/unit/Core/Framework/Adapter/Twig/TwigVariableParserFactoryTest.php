<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopwell\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TwigVariableParserFactory::class)]
class TwigVariableParserFactoryTest extends TestCase
{
    public function testGetParser(): void
    {
        $factory = new TwigVariableParserFactory();
        $twig = new Environment(new ArrayLoader([]));

        $parser = $factory->getParser($twig);
        static::assertSame([], $parser->parse('123-test'));
    }
}
