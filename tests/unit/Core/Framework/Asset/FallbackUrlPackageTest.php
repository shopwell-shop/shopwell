<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\DevOps\Environment\EnvironmentHelper;
use Shopwell\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FallbackUrlPackage::class)]
class FallbackUrlPackageTest extends TestCase
{
    use EnvTestBehaviour;

    public function testCliFallbacksToAppUrl(): void
    {
        $url = $this->createPackage()->getUrl('test');

        static::assertSame(EnvironmentHelper::getVariable('APP_URL') . '/test', $url);
    }

    public function testCliUrlGiven(): void
    {
        $url = $this->createPackage('https://shopwell.cn')->getUrl('test');

        static::assertSame('https://shopwell.cn/test', $url);
    }

    public function testWebFallbackToRequest(): void
    {
        $this->setEnvVars(['HTTP_HOST' => 'test.de']);
        $url = $this->createPackage()->getUrl('test');

        static::assertSame('http://test.de/test', $url);
    }

    public function testGetFromRequestStack(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://test.de'));

        $url = $this->createPackage(requestStack: $requestStack)->getUrl('test');

        static::assertSame('https://test.de/test', $url);
    }

    private function createPackage(string $url = '', ?RequestStack $requestStack = null): FallbackUrlPackage
    {
        return new FallbackUrlPackage([$url], new EmptyVersionStrategy(), $requestStack);
    }
}
