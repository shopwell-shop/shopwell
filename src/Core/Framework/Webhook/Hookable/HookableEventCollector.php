<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Webhook\Hookable;

use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\App\Manifest\Manifest;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\Attribute\Entity as EntityAttribute;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\Entity;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopwell\Core\Framework\Event\BusinessEventCollector;
use Shopwell\Core\Framework\Event\BusinessEventDefinition;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\WebhookException;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class HookableEventCollector implements ResetInterface
{
    private const PRIVILEGES = 'privileges';

    /**
     * @var list<string>|null
     */
    private ?array $hookableEntities = null;

    /**
     * @param iterable<EntityDefinition|Entity> $hookableEntityDefinitions
     * @param iterable<HookableEventDescriber> $hookableEventDescribers
     */
    public function __construct(
        private readonly BusinessEventCollector $businessEventCollector,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        private readonly iterable $hookableEntityDefinitions,
        private readonly iterable $hookableEventDescribers,
    ) {
    }

    /**
     * @return array<string, array{privileges: list<string>}>
     */
    public function getHookableEventNamesWithPrivileges(Context $context, Manifest $manifest): array
    {
        return $this->getEventNamesWithPrivileges($context, $manifest);
    }

    /**
     * @return list<string>
     */
    public function getPrivilegesFromBusinessEventDefinition(BusinessEventDefinition $businessEventDefinition): array
    {
        $privileges = [];
        foreach ($businessEventDefinition->getData() as $data) {
            if ($data['type'] !== 'entity') {
                continue;
            }

            $entityName = $this->definitionRegistry->get($data['entityClass'])->getEntityName();
            $privileges[] = $entityName . ':' . AclRoleDefinition::PRIVILEGE_READ;
        }

        return $privileges;
    }

    /**
     * @return array<string, array{privileges: list<string>}>
     */
    public function getEntityWrittenEventNamesWithPrivileges(): array
    {
        $entityWrittenEventNames = [];
        foreach ($this->getHookableEntities() as $entity) {
            $privileges = [
                self::PRIVILEGES => [$entity . ':' . AclRoleDefinition::PRIVILEGE_READ],
            ];

            $entityWrittenEventNames[$entity . '.written'] = $privileges;
            $entityWrittenEventNames[$entity . '.deleted'] = $privileges;
        }

        return $entityWrittenEventNames;
    }

    /**
     * Dynamically discovers all hookable entities by checking for services tagged with 'shopware.entity.hookable'.
     *
     * @return list<string>
     */
    public function getHookableEntities(): array
    {
        if ($this->hookableEntities !== null) {
            return $this->hookableEntities;
        }

        $hookableEntities = [];

        foreach ($this->hookableEntityDefinitions as $definition) {
            if ($definition instanceof EntityDefinition) {
                $hookableEntities[] = $definition->getEntityName();
            } elseif ($definition instanceof Entity) {
                $reflection = new \ReflectionClass($definition::class);
                $collection = $reflection->getAttributes(EntityAttribute::class);

                if ($collection === []) {
                    continue;
                }

                /** @var EntityAttribute $instance */
                $instance = $collection[0]->newInstance();
                $hookableEntities[] = $instance->name;
            }
        }

        $this->hookableEntities = array_values(array_unique($hookableEntities));

        return $this->hookableEntities;
    }

    public function reset(): void
    {
        $this->hookableEntities = null;
    }

    /**
     * @return array<string, array{privileges: list<string>}>
     */
    private function getEventNamesWithPrivileges(Context $context, Manifest $manifest): array
    {
        return array_merge(
            $this->getEntityWrittenEventNamesWithPrivileges(),
            $this->getBusinessEventNamesWithPrivileges($context),
            $this->getHookableEventNames($manifest)
        );
    }

    /**
     * @return array<string, array{privileges: list<string>}>
     */
    private function getHookableEventNames(Manifest $manifest): array
    {
        $events = [];

        foreach ($this->hookableEventDescribers as $describer) {
            $describerClass = $describer::class;

            foreach ($describer->describeForValidation($manifest) as $eventDescription) {
                if (isset($events[$eventDescription->eventName])) {
                    throw WebhookException::duplicateDescribedEvent(
                        $eventDescription->eventName,
                        $describerClass
                    );
                }

                $events[$eventDescription->eventName] = [self::PRIVILEGES => $eventDescription->privileges];
            }
        }

        return $events;
    }

    /**
     * @return array<string, array{privileges: list<string>}>
     */
    private function getBusinessEventNamesWithPrivileges(Context $context): array
    {
        $response = $this->businessEventCollector->collect($context);

        return array_map(function (BusinessEventDefinition $businessEventDefinition) {
            $privileges = $this->getPrivilegesFromBusinessEventDefinition($businessEventDefinition);

            return [
                self::PRIVILEGES => $privileges,
            ];
        }, $response->getElements());
    }
}
