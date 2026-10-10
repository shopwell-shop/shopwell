<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\System\User\Service;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Locale\LocaleCollection;
use Shopwell\Core\System\User\Service\UserValidationService;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class UserValidationServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    /**
     * @var EntityRepository<LocaleCollection>
     */
    private EntityRepository $localeRepository;

    private UserValidationService $userValidationService;

    protected function setUp(): void
    {
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->localeRepository = static::getContainer()->get('locale.repository');
        $this->userValidationService = static::getContainer()->get(UserValidationService::class);
    }

    public function testIfReturnsTrueForUniqueEmails(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@shopwell.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertTrue($this->userValidationService->checkEmailUnique('some@other.email', $userIdToTest, $context));
        static::assertTrue($this->userValidationService->checkEmailUnique('user@shopwell.cn', $userId, $context));
    }

    public function testIfReturnsFalseForDuplicateEmails(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();

        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@shopwell.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertFalse($this->userValidationService->checkEmailUnique('user@shopwell.cn', $userIdToTest, $context));
    }

    public function testIfReturnsTrueForUniqueUsernames(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@shopwell.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertTrue($this->userValidationService->checkUsernameUnique('other User', $userIdToTest, $context));
        static::assertTrue($this->userValidationService->checkUsernameUnique('some User', $userId, $context));
    }

    public function testIfReturnsFalseForDuplicateUsernames(): void
    {
        $userId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $localeIds = $this->localeRepository->searchIds(new Criteria(), $context)->getIds();
        $firstLocale = array_pop($localeIds);

        $this->userRepository->create([
            [
                'id' => $userId,
                'username' => 'some User',
                'name' => 'first last',
                'localeId' => $firstLocale,
                'email' => 'user@shopwell.cn',
                'password' => TestDefaults::HASHED_PASSWORD,
            ],
        ], $context);

        $userIdToTest = Uuid::randomHex();
        static::assertFalse($this->userValidationService->checkUsernameUnique('some User', $userIdToTest, $context));
    }
}
