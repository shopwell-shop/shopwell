<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\Payment\Security;

use Payum\Core\Model\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\System\Payment\Order\PaymentOrderEntity;
use Shopwell\Core\System\Payment\Security\PaymentGatewayTokenCollection;
use Shopwell\Core\System\Payment\Security\PaymentGatewayTokenEntity;
use Shopwell\Core\System\Payment\Security\PaymentGatewayTokenStorage;

/**
 * @internal
 */
#[CoversClass(PaymentGatewayTokenStorage::class)]
class PaymentGatewayTokenStorageTest extends TestCase
{
    public function testCreateReturnsAToken(): void
    {
        $storage = $this->storage(static::createStub(EntityRepository::class));

        static::assertInstanceOf(Token::class, $storage->create());
    }

    public function testSupportAcceptsOnlyTokens(): void
    {
        $storage = $this->storage(static::createStub(EntityRepository::class));

        static::assertTrue($storage->support(new Token()));
        static::assertFalse($storage->support(new PaymentGatewayTokenEntity()));
    }

    public function testFindMapsTheRowToAToken(): void
    {
        $entity = $this->tokenEntity('hash-1');
        $repository = $this->repositoryReturning([$entity]);

        $token = $this->storage($repository)->find('hash-1');

        static::assertInstanceOf(Token::class, $token);
        static::assertSame('hash-1', $token->getHash());
        static::assertSame('https://target.example', $token->getTargetUrl());
        static::assertSame('https://after.example', $token->getAfterUrl());
        static::assertSame('alipay', $token->getGatewayName());
        static::assertSame($entity->orderId, $token->getDetails()?->getId());
        static::assertSame(PaymentOrderEntity::class, $token->getDetails()?->getClass());
    }

    public function testFindReturnsNullForAnUnknownHash(): void
    {
        $storage = $this->storage($this->repositoryReturning([]));

        static::assertNull($storage->find('missing'));
    }

    public function testFindByReturnsTheTokenForAHash(): void
    {
        $storage = $this->storage($this->repositoryReturning([$this->tokenEntity('hash-1')]));

        $tokens = $storage->findBy(['hash' => 'hash-1']);

        static::assertCount(1, $tokens);
        static::assertSame('hash-1', $tokens[0]->getHash());
    }

    public function testFindByWithoutAHashReturnsNothing(): void
    {
        $storage = $this->storage($this->repositoryReturning([$this->tokenEntity('hash-1')]));

        static::assertSame([], $storage->findBy([]));
    }

    public function testIdentifyUsesTheHashAsIdentity(): void
    {
        $token = new Token();
        $token->setHash('hash-1');

        $identity = $this->storage(static::createStub(EntityRepository::class))->identify($token);

        static::assertSame('hash-1', $identity->getId());
        static::assertSame(Token::class, $identity->getClass());
    }

    public function testUpdateCreatesARowWhenNoneExists(): void
    {
        $token = new Token();
        $token->setHash('hash-1');
        $token->setGatewayName('alipay');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')->willReturn($this->emptyIdSearchResult());
        $repository->expects($this->once())
            ->method('create')
            ->with(static::callback(static function (array $rows): bool {
                static::assertCount(1, $rows);
                static::assertSame('hash-1', $rows[0]['hash']);
                static::assertArrayHasKey('id', $rows[0]);
                // the dal fills created_at on insert, the storage must not copy that behaviour
                static::assertArrayNotHasKey('createdAt', $rows[0]);

                return true;
            }));

        $this->storage($repository)->update($token);
    }

    public function testUpdateWritesIntoTheExistingRow(): void
    {
        $token = new Token();
        $token->setHash('hash-1');

        $existingId = Uuid::randomHex();
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')->willReturn(IdSearchResult::fromIds([$existingId], new Criteria(), Context::createDefaultContext()));
        $repository->expects($this->once())
            ->method('update')
            ->with(static::callback(static function (array $rows) use ($existingId): bool {
                static::assertSame($existingId, $rows[0]['id']);

                return true;
            }));

        $this->storage($repository)->update($token);
    }

    public function testDeleteRemovesTheRowByHash(): void
    {
        $token = new Token();
        $token->setHash('hash-1');

        $existingId = Uuid::randomHex();
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')->willReturn(IdSearchResult::fromIds([$existingId], new Criteria(), Context::createDefaultContext()));
        $repository->expects($this->once())
            ->method('delete')
            ->with([['id' => $existingId]]);

        $this->storage($repository)->delete($token);
    }

    public function testDeleteOfAnUnknownTokenDoesNothing(): void
    {
        $token = new Token();
        $token->setHash('hash-1');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('searchIds')->willReturn($this->emptyIdSearchResult());
        $repository->expects($this->never())->method('delete');

        $this->storage($repository)->delete($token);
    }

    /**
     * @param EntityRepository<PaymentGatewayTokenCollection> $repository
     */
    private function storage(EntityRepository $repository): PaymentGatewayTokenStorage
    {
        return new PaymentGatewayTokenStorage($repository);
    }

    /**
     * @param list<PaymentGatewayTokenEntity> $entities
     *
     * @return EntityRepository<PaymentGatewayTokenCollection>
     */
    private function repositoryReturning(array $entities): EntityRepository
    {
        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(new EntitySearchResult(
            PaymentGatewayTokenEntity::ENTITY_NAME,
            \count($entities),
            new EntityCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext(),
        ));

        return $repository;
    }

    private function tokenEntity(string $hash): PaymentGatewayTokenEntity
    {
        $entity = new PaymentGatewayTokenEntity();
        $entity->id = Uuid::randomHex();
        $entity->setUniqueIdentifier($entity->id);
        $entity->hash = $hash;
        $entity->orderId = Uuid::randomHex();
        $entity->targetUrl = 'https://target.example';
        $entity->afterUrl = 'https://after.example';
        $entity->gatewayName = 'alipay';

        return $entity;
    }

    private function emptyIdSearchResult(): IdSearchResult
    {
        return IdSearchResult::fromIds([], new Criteria(), Context::createDefaultContext());
    }
}
