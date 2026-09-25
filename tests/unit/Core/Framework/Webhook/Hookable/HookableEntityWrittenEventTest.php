<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Customer\CustomerDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Defaults;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopwell\Core\Framework\Webhook\Hookable\HookableEntityWrittenEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(HookableEntityWrittenEvent::class)]
class HookableEntityWrittenEventTest extends TestCase
{
    public function testGetter(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent($entityId));

        static::assertSame('product.written', $event->getName());
        static::assertSame([
            [
                'entity' => 'product',
                'operation' => 'delete',
                'primaryKey' => $entityId,
                'updatedFields' => [],
            ],
        ], $event->getWebhookPayload(new AppEntity()));
    }

    public function testGetterWithVersionId(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent(
            $entityId,
            ['versionId' => Defaults::LIVE_VERSION]
        ));

        static::assertSame('product.written', $event->getName());
        static::assertSame([
            [
                'entity' => 'product',
                'operation' => 'delete',
                'primaryKey' => $entityId,
                'updatedFields' => ['versionId'],
                'versionId' => Defaults::LIVE_VERSION,
            ],
        ], $event->getWebhookPayload(new AppEntity()));
    }

    public function testIsAllowed(): void
    {
        $entityId = Uuid::randomHex();
        $event = HookableEntityWrittenEvent::fromWrittenEvent($this->getEntityWrittenEvent($entityId));

        $allowedPermissions = new AclPrivilegeCollection([
            ProductDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertTrue($event->isAllowed(
            Uuid::randomHex(),
            $allowedPermissions
        ));

        $notAllowedPermissions = new AclPrivilegeCollection([
            CustomerDefinition::ENTITY_NAME . ':' . AclRoleDefinition::PRIVILEGE_READ,
        ]);
        static::assertFalse($event->isAllowed(
            Uuid::randomHex(),
            $notAllowedPermissions
        ));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function getEntityWrittenEvent(string $entityId, array $payload = []): EntityWrittenEvent
    {
        $context = Context::createDefaultContext();

        return new EntityWrittenEvent(
            ProductDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    $entityId,
                    $payload,
                    ProductDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    null,
                    null
                ),
            ],
            $context
        );
    }
}
