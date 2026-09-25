<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Hookable;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Event\EventData\ArrayType;
use Shopwell\Core\Framework\Event\EventData\EntityCollectionType;
use Shopwell\Core\Framework\Event\EventData\EntityType;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ObjectType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\FrameworkException;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopwell\Core\Framework\Webhook\BusinessEventEncoder;
use Shopwell\Core\Framework\Webhook\Hookable;

/**
 * @internal
 */
#[Package('framework')]
class HookableBusinessEvent implements Hookable
{
    private function __construct(
        private readonly FlowEventAware $flowEventAware,
        private readonly BusinessEventEncoder $businessEventEncoder
    ) {
    }

    public static function fromBusinessEvent(
        FlowEventAware $flowEventAware,
        BusinessEventEncoder $businessEventEncoder
    ): self {
        return new self($flowEventAware, $businessEventEncoder);
    }

    public function getName(): string
    {
        return $this->flowEventAware->getName();
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return $this->businessEventEncoder->encode($this->flowEventAware);
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        foreach ($this->flowEventAware->getAvailableData()->toArray() as $dataType) {
            if ($dataType[EventDataCollection::HIDDEN_FROM_WEBHOOK] ?? false) {
                continue;
            }

            if (!$this->checkPermissionsForDataType($dataType, $permissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $dataType
     */
    private function checkPermissionsForDataType(array $dataType, AclPrivilegeCollection $permissions): bool
    {
        $type = $dataType['type'] ?? null;
        $data = $dataType['data'] ?? null;
        if ($type === ObjectType::TYPE && \is_array($data) && $data !== []) {
            foreach ($data as $nested) {
                if (!$this->checkPermissionsForDataType($nested, $permissions)) {
                    return false;
                }
            }
        }

        $of = $dataType['of'] ?? null;
        if ($type === ArrayType::TYPE && \is_array($of) && $of !== [] && !$this->checkPermissionsForDataType($of, $permissions)) {
            return false;
        }

        if ($type === EntityType::TYPE || $type === EntityCollectionType::TYPE) {
            $entityDefinitionClass = $dataType['entityClass'] ?? null;
            if (!\is_string($entityDefinitionClass) || !is_a($entityDefinitionClass, EntityDefinition::class, true)) {
                throw FrameworkException::invalidEventData(\sprintf(
                    '"entityClass" value of flow event data type "%s" must be a class string of type "%s"',
                    EntityType::TYPE,
                    EntityDefinition::class
                ));
            }

            $definition = new $entityDefinitionClass();
            if (!$permissions->isAllowed($definition->getEntityName(), AclRoleDefinition::PRIVILEGE_READ)) {
                return false;
            }
        }

        return true;
    }
}
