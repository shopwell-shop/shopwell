<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Routing\RoutingException;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopwell\Core\System\Language\LanguageEntity;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopwell\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopwell\Core\System\SalesChannel\ContextTokenResponse;
use Shopwell\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopwell\Core\System\SalesChannel\SalesChannelContext;
use Shopwell\Storefront\Controller\ContextController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextController::class)]
class ContextControllerUnitTest extends TestCase
{
    public function testSwitchLangNoArgument(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $this->expectExceptionObject(RoutingException::missingRequestParameter('languageId'));

        $controller->switchLanguage(new Request(), static::createStub(SalesChannelContext::class));
    }

    public function testSwitchLangNoString(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $this->expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));

        $controller->switchLanguage(
            new Request([], ['languageId' => 1]),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testSwitchLangNoValidUuid(): void
    {
        $controller = new ContextController(
            static::createStub(ContextSwitchRoute::class),
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $this->expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));

        $controller->switchLanguage(
            new Request([], ['languageId' => 'noUuid']),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testSwitchLangNotFound(): void
    {
        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );
        $controller = new ContextController(
            $contextSwitchRoute,
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class)
        );

        $notExistingLang = Uuid::randomHex();

        $this->expectExceptionObject(RoutingException::languageNotFound($notExistingLang));

        $controller->switchLanguage(
            new Request([], ['languageId' => $notExistingLang]),
            static::createStub(SalesChannelContext::class)
        );
    }

    public function testSwitchCustomerChange(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new SalesChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setSalesChannelDomains(new SalesChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->once())->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock
        );

        $contextMock = static::createStub(SalesChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => null]),
            $contextMock
        );
    }

    public function testSwitchRedirectToNotExistingTarget(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new SalesChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setSalesChannelDomains(new SalesChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->exactly(2))->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock
        );

        $notExistingRedirectTo = 'frontend.homer.page';

        $contextMock = static::createStub(SalesChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => $notExistingRedirectTo]),
            $contextMock
        );
    }

    public function testSwitchRedirectToExistingTarget(): void
    {
        $language = new LanguageEntity();
        $language->setUniqueIdentifier(Uuid::randomHex());
        $scDomain = new SalesChannelDomainEntity();
        $scDomain->setUniqueIdentifier(Uuid::randomHex());
        $scDomain->setUrl('http://localhost');
        $language->setSalesChannelDomains(new SalesChannelDomainCollection([$scDomain]));

        $routerMock = $this->createMock(RouterInterface::class);
        $routerMock->expects($this->once())->method('getContext')->willReturn(new RequestContext());
        $routerMock->expects($this->exactly(2))->method('generate')->willReturn('http://localhost');
        $requestStackMock = $this->createMock(RequestStack::class);
        $requestStackMock->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())->method('switchContext')->willReturn(
            new ContextTokenResponse(Uuid::randomHex(), 'http://localhost')
        );

        $controller = new ContextController(
            $contextSwitchRoute,
            $requestStackMock,
            $routerMock
        );

        $existingRedirectTo = 'frontend.home.page';

        $contextMock = static::createStub(SalesChannelContext::class);

        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => $existingRedirectTo]),
            $contextMock
        );
    }
}
