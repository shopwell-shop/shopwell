<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\Twig\Filter\ReplaceRecursiveFilter;
use Shopwell\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ReplaceRecursiveFilter::class)]
class ReplaceRecursiveFilterTest extends TestCase
{
    public function testReplace(): void
    {
        $env = new Environment(new ArrayLoader(['test' => '{{ {"berries": ["blueberry"]}|replace_recursive({"berries": ["strawberry", "blackberry"]})|json_encode|raw }}']));
        $env->addExtension(new ReplaceRecursiveFilter());

        static::assertSame('{"berries":["strawberry","blackberry"]}', $env->render('test'));
    }
}
