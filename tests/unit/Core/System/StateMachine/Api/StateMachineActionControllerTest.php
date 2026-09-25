<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\System\StateMachine\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Api\Context\AdminApiSource;
use Shopwell\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopwell\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopwell\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopwell\Core\System\StateMachine\Api\StateMachineActionController;
use Shopwell\Core\System\StateMachine\StateMachineRegistry;
use Shopwell\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StateMachineActionController::class)]
class StateMachineActionControllerTest extends TestCase
{
    public function testTransitionWithoutPrivileges(): void
    {
        $this->expectExceptionObject(new MissingPrivilegeException(['order:update']));

        $controller = new StateMachineActionController(
            static::createStub(StateMachineRegistry::class),
            static::createStub(DefinitionInstanceRegistry::class),
        );
        $controller->transitionState(
            new Request(),
            Context::createDefaultContext(new AdminApiSource(null)),
            static::createStub(ResponseFactoryInterface::class),
            'order',
            '1234',
            'process',
        );
    }

    public function testGetAvailableTransitionsWithoutPrivileges(): void
    {
        $this->expectExceptionObject(new MissingPrivilegeException(['order:read']));

        $controller = new StateMachineActionController(
            static::createStub(StateMachineRegistry::class),
            static::createStub(DefinitionInstanceRegistry::class),
        );
        $controller->getAvailableTransitions(
            new Request(),
            Context::createDefaultContext(new AdminApiSource(null)),
            'order',
            '1234',
        );
    }

    public function testTransitionUseData(): void
    {
        $stateMachineRegistry = $this->createMock(StateMachineRegistry::class);

        $source = new AdminApiSource(null);
        $source->setPermissions(['order:update']);
        $context = Context::createDefaultContext($source);

        $stateMachineStates = new StateMachineStateCollection();
        $toPlace = new StateMachineStateEntity();
        $stateMachineStates->set('toPlace', $toPlace);

        $expectedTransition = new Transition('order', '1234', 'process', 'abc', 'def');

        $stateMachineRegistry->expects($this->once())
            ->method('transition')
            ->with(
                static::equalTo($expectedTransition),
                $context
            )
            ->willReturn($stateMachineStates);

        $controller = new StateMachineActionController(
            $stateMachineRegistry,
            static::createStub(DefinitionInstanceRegistry::class),
        );

        $request = new Request(query: ['stateFieldName' => 'abc'], request: ['internalComment' => 'def']);

        $controller->transitionState(
            $request,
            $context,
            static::createStub(ResponseFactoryInterface::class),
            'order',
            '1234',
            'process',
        );
    }
}
