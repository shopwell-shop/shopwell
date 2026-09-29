<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Framework\Store\Authentication;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Store\Authentication\LocaleProvider;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class LocaleProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private LocaleProvider $localeProvider;

    protected function setUp(): void
    {
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->localeProvider = static::getContainer()->get(LocaleProvider::class);
    }

    public function testGetLocaleFromContextReturnsLocaleFromUser(): void
    {
        $userId = Uuid::randomHex();
        $userLocale = 'zh-CN-1';

        $this->userRepository->create([[
            'id' => $userId,
            'username' => 'testUser',
            'firstName' => 'first',
            'lastName' => 'last',
            'email' => 'first@last.de',
            'password' => TestDefaults::HASHED_PASSWORD,
            'locale' => [
                'code' => $userLocale,
                'name' => 'testLocale',
                'territory' => 'somewhere',
            ],
        ]], Context::createDefaultContext());

        $context = Context::createDefaultContext(new AdminApiSource($userId));

        $locale = $this->localeProvider->getLocaleFromContext($context);

        static::assertSame($userLocale, $locale);
    }

    public function testGetLocaleFromContextReturnsEnglishForSystemContext(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(Context::createDefaultContext());

        static::assertSame('en-GB', $locale);
    }

    public function testGetLocaleFromContextReturnsEnglishForIntegrations(): void
    {
        $locale = $this->localeProvider->getLocaleFromContext(
            Context::createDefaultContext(new AdminApiSource(null, Uuid::randomHex()))
        );

        static::assertSame('en-GB', $locale);
    }
}
