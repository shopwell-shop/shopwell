<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Store\Authentication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\LocaleProvider;
use Shopwell\Core\System\Locale\LocaleEntity;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserDefinition;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LocaleProvider::class)]
class LocaleProviderTest extends TestCase
{
    public function testGetLocaleFromContextReturnsEnGbInSystemSource(): void
    {
        $provider = new LocaleProvider(static::createStub(EntityRepository::class));

        static::assertSame('en-GB', $provider->getLocaleFromContext(Context::createDefaultContext()));
    }

    public function testGetLocaleFromContextReturnsEnGbIfNoUserIsAssociated(): void
    {
        $provider = new LocaleProvider(static::createStub(EntityRepository::class));

        static::assertSame(
            'en-GB',
            $provider->getLocaleFromContext(Context::createDefaultContext(
                new AdminApiSource(null, 'i-am-an-integration')
            ))
        );
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id', null));

        $userLocale = new LocaleEntity();
        $userLocale->setCode('user-locale');

        $user = new UserEntity();
        $user->setUniqueIdentifier('user-identifier');
        $user->setLocale($userLocale);

        $userRepository = static::createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([$user]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($userRepository);

        static::assertSame('user-locale', $provider->getLocaleFromContext($context));
    }

    public function testGetLocaleFromContextThrowsIfAssociatedUserCanNotBeFound(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource('user-id', null));

        $userRepository = static::createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([]),
                null,
                new Criteria(),
                $context
            ));

        $provider = new LocaleProvider($userRepository);

        static::expectException(EntityNotFoundException::class);
        $provider->getLocaleFromContext($context);
    }
}
