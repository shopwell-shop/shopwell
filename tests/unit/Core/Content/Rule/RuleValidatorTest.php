<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopwell\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopwell\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopwell\Core\Content\Rule\RuleValidator;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopwell\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleValidator::class)]
class RuleValidatorTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'preValidate'],
            RuleValidator::getSubscribedEvents()
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $expectedViolationPointers
     */
    #[DataProvider('updateConditionValueProvider')]
    public function testItValidatesUpdateConditionValues(array $payload, array $expectedViolationPointers): void
    {
        $context = Context::createDefaultContext();
        $conditionId = Uuid::randomHex();
        $conditionIdBytes = Uuid::fromHexToBytes($conditionId);
        $definition = $this->getRuleConditionDefinition();
        $storedCondition = $this->createCustomerGroupCondition($conditionId);

        $searchResult = new EntitySearchResult(
            RuleConditionDefinition::ENTITY_NAME,
            1,
            new RuleConditionCollection([$storedCondition]),
            null,
            new Criteria([$conditionId]),
            $context
        );

        $ruleConditionRepository = $this->createMock(EntityRepository::class);
        $ruleConditionRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $validator = new RuleValidator(
            Validation::createValidator(),
            new RuleConditionRegistry([new AlwaysValidRule(), new CustomerGroupRule()]),
            $ruleConditionRepository,
            static::createStub(EntityRepository::class)
        );

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $definition,
                    $payload,
                    ['id' => $conditionIdBytes],
                    EntityExistence::createForEntity(
                        RuleConditionDefinition::ENTITY_NAME,
                        ['id' => $conditionIdBytes]
                    ),
                    '/0'
                ),
            ]
        );

        $validator->preValidate($event);

        $violations = iterator_to_array($event->getExceptions()->getErrors());
        $violationPointers = array_column(array_column($violations, 'source'), 'pointer');

        static::assertSame($expectedViolationPointers, $violationPointers);
    }

    /**
     * @return iterable<string, array{payload: array<string, mixed>, expectedViolationPointers: list<string>}>
     */
    public static function updateConditionValueProvider(): iterable
    {
        yield 'uses explicit null as empty value' => [
            'payload' => [
                'type' => AlwaysValidRule::RULE_NAME,
                'value' => null,
            ],
            'expectedViolationPointers' => [],
        ];

        yield 'uses explicit JSON value' => [
            'payload' => [
                'type' => CustomerGroupRule::RULE_NAME,
                'value' => json_encode([
                    'customerGroupIds' => [Uuid::randomHex()],
                    'operator' => CustomerGroupRule::OPERATOR_EQ,
                ], \JSON_THROW_ON_ERROR),
            ],
            'expectedViolationPointers' => [],
        ];

        yield 'uses stored value when update payload has no value key' => [
            'payload' => [
                'type' => AlwaysValidRule::RULE_NAME,
            ],
            'expectedViolationPointers' => [
                '/0/value/customerGroupIds',
                '/0/value/operator',
            ],
        ];

        yield 'validates explicit null as empty value for required rule fields' => [
            'payload' => [
                'type' => CustomerGroupRule::RULE_NAME,
                'value' => null,
            ],
            'expectedViolationPointers' => [
                '/0/value/customerGroupIds',
                '/0/value/operator',
            ],
        ];
    }

    private function createCustomerGroupCondition(string $id): RuleConditionEntity
    {
        $condition = new RuleConditionEntity();
        $condition->setId($id);
        $condition->setType(CustomerGroupRule::RULE_NAME);
        $condition->setValue([
            'customerGroupIds' => [Uuid::randomHex()],
            'operator' => CustomerGroupRule::OPERATOR_EQ,
        ]);

        return $condition;
    }

    private function getRuleConditionDefinition(): RuleConditionDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [RuleConditionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(RuleConditionDefinition::class);
        static::assertInstanceOf(RuleConditionDefinition::class, $definition);

        return $definition;
    }
}
