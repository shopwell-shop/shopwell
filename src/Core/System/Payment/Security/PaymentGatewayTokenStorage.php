<?php declare(strict_types=1);

namespace Shopwell\Core\System\Payment\Security;

use Payum\Core\Model\Identity;
use Payum\Core\Model\Token;
use Payum\Core\Storage\AbstractStorage;
use Payum\Core\Storage\IdentityInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;

/**
 * Keeps the payum tokens of the gateway in the database instead of on disk, so every node of a multi
 * instance installation resolves the same token and an operator can inspect or clean them.
 *
 * The model stays `Payum\Core\Model\Token`, only the persistence is ours: the platform speaks payum
 * on the outside and the data abstraction layer on the inside, and this class is the seam between
 * them. A token is identified by its hash, exactly like the filesystem storage of payum does it, so
 * `find()`, `identify()` and `delete()` keep the contract the gateway expects.
 *
 * @internal
 */
#[Package('framework')]
final class PaymentGatewayTokenStorage extends AbstractStorage
{
    /**
     * @param EntityRepository<PaymentGatewayTokenCollection> $repository
     */
    public function __construct(
        private readonly EntityRepository $repository,
    ) {
        parent::__construct(Token::class);
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return list<Token>
     */
    public function findBy(array $criteria): array
    {
        $hash = $criteria['hash'] ?? null;
        if (!\is_string($hash) || $hash === '') {
            return [];
        }

        $token = $this->loadByHash($hash);

        return $token === null ? [] : [$token];
    }

    protected function doFind(mixed $id): ?Token
    {
        return \is_string($id) && $id !== '' ? $this->loadByHash($id) : null;
    }

    protected function doUpdateModel(mixed $model): void
    {
        \assert($model instanceof Token);

        $hash = $model->getHash();
        if ($hash === '') {
            return;
        }

        $details = $model->getDetails();
        // the token refers to the payment order and to nothing else: a token whose details are some
        // other model would point at a row that is not an order, and the column is a real reference
        $orderId = $details instanceof IdentityInterface
            && $details->getClass() === PaymentOrderEntity::class
            && \is_string($details->getId())
                ? $details->getId()
                : null;

        $context = Context::createDefaultContext();
        $payload = [
            'hash' => $hash,
            'orderId' => $orderId,
            'targetUrl' => $model->getTargetUrl(),
            'afterUrl' => $model->getAfterUrl(),
            'gatewayName' => $model->getGatewayName(),
        ];

        $id = $this->findIdByHash($hash, $context);
        if ($id === null) {
            // created_at is filled by the dal, writing it here would fork the platform behaviour
            $this->repository->create([['id' => Uuid::randomHex(), ...$payload]], $context);

            return;
        }

        $this->repository->update([['id' => $id, ...$payload]], $context);
    }

    protected function doDeleteModel(mixed $model): void
    {
        \assert($model instanceof Token);

        $hash = $model->getHash();
        if ($hash === '') {
            return;
        }

        $context = Context::createDefaultContext();
        $id = $this->findIdByHash($hash, $context);
        if ($id !== null) {
            $this->repository->delete([['id' => $id]], $context);
        }
    }

    protected function doGetIdentity(mixed $model): IdentityInterface
    {
        \assert($model instanceof Token);

        return new Identity($model->getHash(), $model);
    }

    private function loadByHash(string $hash): ?Token
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $hash));
        $criteria->setLimit(1);

        $entity = $this->repository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        if (!$entity instanceof PaymentGatewayTokenEntity) {
            return null;
        }

        $token = new Token();
        $token->setHash($entity->hash);

        if ($entity->targetUrl !== null) {
            $token->setTargetUrl($entity->targetUrl);
        }

        if ($entity->afterUrl !== null) {
            $token->setAfterUrl($entity->afterUrl);
        }

        if ($entity->gatewayName !== null) {
            $token->setGatewayName($entity->gatewayName);
        }

        if ($entity->orderId !== null) {
            $token->setDetails(new Identity($entity->orderId, PaymentOrderEntity::class));
        }

        return $token;
    }

    private function findIdByHash(string $hash, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('hash', $hash));
        $criteria->setLimit(1);

        return $this->repository->searchIds($criteria, $context)->firstId();
    }
}
