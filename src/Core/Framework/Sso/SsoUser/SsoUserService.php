<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Sso\SsoUser;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Util\Random;
use Shopwell\Core\System\User\UserCollection;
use Shopwell\Core\System\User\UserEntity;

/**
 * @internal
 */
#[Package('framework')]
class SsoUserService
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
    ) {
    }

    public function inviteUser(string $email, string $localeId, Context $context): void
    {
        $user = $this->getUserByEmail($email, $context);

        if ($user === null) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($email, $localeId): void {
                $this->userRepository->create([$this->createInitialUserData($email, $localeId)], $context);
            });
        }
    }

    /**
     * @return array{username: string, email: string, firstName: string, lastName: string, password: string, localeId: string}
     */
    private function createInitialUserData(string $email, string $localeId): array
    {
        return [
            'username' => $email,
            'email' => $email,
            'firstName' => $email,
            'lastName' => $email,
            'password' => Random::getAlphanumericString(32),
            'localeId' => $localeId,
        ];
    }

    private function getUserByEmail(string $email, Context $context): ?UserEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        return $this->userRepository->search($criteria, $context)->getEntities()->first();
    }
}
