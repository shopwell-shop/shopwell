<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\ActionButton;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopwell\Core\Framework\App\ActionButton\AppAction;
use Shopwell\Core\Framework\App\ActionButton\Executor;
use Shopwell\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\AppException;
use Shopwell\Core\Framework\App\Payload\Source;
use Shopwell\Core\Framework\App\ShopId\ShopIdProvider;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request as SfRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Executor::class)]
class ExecutorTest extends TestCase
{
    public function testConnectionProblemsGotConverted(): void
    {
        $requestStack = static::createStub(RequestStack::class);
        $requestStack
            ->method('getCurrentRequest')
            ->willReturn(new SfRequest());

        $guzzleClient = new Client([
            'handler' => static function (): void {
                throw new ConnectException('Connection problems', new Request('POST', 'https://example.com'));
            },
        ]);

        $executor = new Executor(
            $guzzleClient,
            static::createStub(LoggerInterface::class),
            static::createStub(ActionButtonResponseFactory::class),
            static::createStub(ShopIdProvider::class),
            static::createStub(RouterInterface::class),
            $requestStack,
            static::createStub(KernelInterface::class),
            new NativeClock()
        );

        $this->expectExceptionObject(AppException::actionButtonProcessException('123123123', 'ActionButton remote execution failed due to connection problems'));

        $app = new AppEntity();
        $app->setAppSecret('devSecret');

        $appAction = new AppAction($app, new Source('https://localhost', 'asd', '1.0.0'), 'https://example.com', 'GET', 'action-id', [Uuid::randomHex()], '123123123');

        $executor->execute(
            $appAction,
            Context::createDefaultContext()
        );
    }
}
