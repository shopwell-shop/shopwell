<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Adapter\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Adapter\AdapterException;
use Shopwell\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateScopeDetector::class)]
class TemplateScopeDetectorTest extends TestCase
{
    public function testDetectWithNoRequest(): void
    {
        $detector = new TemplateScopeDetector(new RequestStack());
        static::assertSame([TemplateScopeDetector::DEFAULT_SCOPE], $detector->getScopes());
    }

    public function testDetectWithEmptyRequest(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request());
        $detector = new TemplateScopeDetector($stack);
        static::assertSame([TemplateScopeDetector::DEFAULT_SCOPE], $detector->getScopes());
    }

    public function testDetectWithString(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request([], [], [TemplateScopeDetector::SCOPES_ATTRIBUTE => 'foo']));
        $detector = new TemplateScopeDetector($stack);
        static::assertSame(['foo'], $detector->getScopes());
    }

    public function testDetectWithArray(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request([], [], [TemplateScopeDetector::SCOPES_ATTRIBUTE => ['foo', 'bar']]));
        $detector = new TemplateScopeDetector($stack);
        static::assertSame(['foo', 'bar'], $detector->getScopes());
    }

    public function testDetectThrowsExceptionWithInvalidValue(): void
    {
        $stack = new RequestStack();
        $stack->push(new Request([], [], [TemplateScopeDetector::SCOPES_ATTRIBUTE => true]));
        $detector = new TemplateScopeDetector($stack);
        static::expectException(AdapterException::class);
        $detector->getScopes();
    }
}
