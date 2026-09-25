<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Framework\Script\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Script\Execution\Script;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Controller\ScriptController;
use Shopwell\Storefront\Framework\Script\Api\StorefrontHook;
use Shopwell\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacade;
use Shopwell\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacadeHookFactory;
use Shopwell\Storefront\Page\Page;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontScriptResponseFactoryFacadeHookFactory::class)]
class StorefrontScriptResponseFactoryFacadeHookFactoryTest extends TestCase
{
    #[TestDox('getName returns the documented "response" script-service identifier')]
    public function testGetNameIsResponse(): void
    {
        static::assertSame('response', $this->buildFactory()->getName());
    }

    #[TestDox('factory() builds the Storefront facade and forwards the hook SalesChannelContext into render()')]
    public function testFactoryBuildsStorefrontFacadeWithSalesChannelContext(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $hook = new StorefrontHook('test-hook', [], [], new Page(), $salesChannelContext);

        $scriptController = $this->createMock(ScriptController::class);
        $scriptController->expects($this->once())
            ->method('renderStorefrontForScript')
            ->with('@Storefront/foo.html.twig', [])
            ->willReturn(new Response('ok'));

        $facade = $this->buildFactory($scriptController)->factory($hook, static::createStub(Script::class));

        static::assertInstanceOf(StorefrontScriptResponseFactoryFacade::class, $facade);
        // render() succeeding proves the SalesChannelContext was forwarded (otherwise it throws).
        $facade->render('@Storefront/foo.html.twig');
    }

    private function buildFactory(?ScriptController $scriptController = null): StorefrontScriptResponseFactoryFacadeHookFactory
    {
        return new StorefrontScriptResponseFactoryFacadeHookFactory(
            static::createStub(RouterInterface::class),
            $scriptController ?? static::createStub(ScriptController::class),
        );
    }
}
