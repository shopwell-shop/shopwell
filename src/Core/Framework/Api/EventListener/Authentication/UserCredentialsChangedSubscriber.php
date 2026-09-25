<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Api\EventListener\Authentication;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\OAuth\AuthCodeRepository;
use Shopwell\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\User\UserEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class UserCredentialsChangedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly AuthCodeRepository $authCodeRepository,
        private readonly Connection $connection,
        private readonly ClockInterface $clock
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserEvents::USER_WRITTEN_EVENT => 'onUserWritten',
            UserEvents::USER_DELETED_EVENT => 'onUserDeleted',
        ];
    }

    public function onUserWritten(EntityWrittenEvent $event): void
    {
        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            if ($this->userCredentialsChanged($payload)) {
                $this->revokeUserTokens($payload['id']);
                $this->updateLastUpdatedPasswordTimestamp($payload['id']);
            }
        }
    }

    public function onUserDeleted(EntityDeletedEvent $event): void
    {
        $ids = $event->getIds();

        foreach ($ids as $id) {
            $this->revokeUserTokens($id);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function userCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']) || (\array_key_exists('active', $payload) && $payload['active'] === false);
    }

    private function updateLastUpdatedPasswordTimestamp(string $userId): void
    {
        $this->connection->update('user', [
            'last_updated_password_at' => $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'id' => Uuid::fromHexToBytes($userId),
        ]);
    }

    private function revokeUserTokens(string $userId): void
    {
        $this->refreshTokenRepository->revokeRefreshTokensForUser($userId);
        $this->authCodeRepository->revokeAuthCodesForUser($userId);
    }
}
