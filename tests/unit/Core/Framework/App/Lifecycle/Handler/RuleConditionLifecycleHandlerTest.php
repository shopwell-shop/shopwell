<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\App\Lifecycle\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopwell\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopwell\Core\Framework\App\Lifecycle\Handler\RuleConditionLifecycleHandler;
use Shopwell\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Shopwell\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopwell\Tests\Unit\Core\Framework\App\AppFixture;
use Shopwell\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RuleConditionLifecycleHandler::class)]
class RuleConditionLifecycleHandlerTest extends TestCase
{
    public function testPersistCreatesRuleConditionsFromManifest(): void
    {
        $app = $this->createAppWithRuleConditions();
        $conditionRepository = $this->createConditionRepository();

        $scriptReader = $this->createMock(ScriptFileReader::class);
        $scriptReader->expects($this->exactly(2))
            ->method('getScriptContent')
            ->with($app, '/rule-conditions/mock.twig')
            ->willReturn('{% return true %}');

        $manifest = ManifestFixture::empty()
            ->withName('withRuleConditions')
            ->withRuleCondition('testcondition0')
            ->withRuleCondition('testcondition1');

        $persister = new RuleConditionLifecycleHandler(
            $scriptReader,
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->install(AppFixture::createInstallContext($app, $manifest));

        $payloads = $this->indexPayloadsByIdentifier($conditionRepository->getPayloads(StaticEntityRepository::UPSERT));

        static::assertCount(2, $payloads);

        foreach ($payloads as $identifier => $payload) {
            static::assertStringContainsString('app\withRuleConditions_', $identifier);
            static::assertSame($app->getId(), $payload['appId']);
            static::assertSame('{% return true %}', $payload['script']);
            static::assertTrue($payload['active']);
            static::assertSame([], $payload['config']);
        }
    }

    public function testPersistUpdatesExistingRuleConditionsAndDeletesRemovedOnes(): void
    {
        $existingConditionId = Uuid::randomHex();
        $removedConditionId = Uuid::randomHex();
        $app = $this->createAppWithRuleConditions(
            $this->createCondition($existingConditionId, 'app\withRuleConditions_testcondition0'),
            $this->createCondition($removedConditionId, 'app\withRuleConditions_testcondition1'),
        );

        $conditionRepository = $this->createConditionRepository();

        $scriptReader = $this->createMock(ScriptFileReader::class);
        $scriptReader->expects($this->once())
            ->method('getScriptContent')
            ->with($app, '/rule-conditions/mock.twig')
            ->willReturn('{% return true %}');

        $manifest = ManifestFixture::empty()
            ->withName('withRuleConditions')
            ->withRuleCondition('testcondition0');

        $persister = new RuleConditionLifecycleHandler(
            $scriptReader,
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->update(AppFixture::createUpdateContext($app, $manifest));

        $upserts = $conditionRepository->getPayloads(StaticEntityRepository::UPSERT);

        static::assertCount(1, $upserts);
        static::assertSame($existingConditionId, $upserts[0]['id']);
        static::assertSame('app\withRuleConditions_testcondition0', $upserts[0]['identifier']);
        static::assertSame([], $upserts[0]['config']);

        static::assertSame([['id' => $removedConditionId]], $conditionRepository->getPayloads(StaticEntityRepository::DELETE));
    }

    public function testActivateUpdatesInactiveRuleConditions(): void
    {
        $app = $this->createAppWithRuleConditions();
        $conditionIds = [Uuid::randomHex(), Uuid::randomHex()];
        $conditionRepository = $this->createConditionRepository(...$conditionIds);

        $persister = new RuleConditionLifecycleHandler(
            static::createStub(ScriptFileReader::class),
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->activate(new AppActivationContext($app, Context::createDefaultContext()));

        static::assertSame([
            ['id' => $conditionIds[0], 'active' => true],
            ['id' => $conditionIds[1], 'active' => true],
        ], $conditionRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testDeactivateUpdatesActiveRuleConditions(): void
    {
        $app = $this->createAppWithRuleConditions();
        $conditionIds = [Uuid::randomHex(), Uuid::randomHex()];
        $conditionRepository = $this->createConditionRepository(...$conditionIds);

        $persister = new RuleConditionLifecycleHandler(
            static::createStub(ScriptFileReader::class),
            $conditionRepository,
            AppFixture::createAppRepository($app),
        );

        $persister->deactivate(new AppActivationContext($app, Context::createDefaultContext()));

        static::assertSame([
            ['id' => $conditionIds[0], 'active' => false],
            ['id' => $conditionIds[1], 'active' => false],
        ], $conditionRepository->getPayloads(StaticEntityRepository::UPDATE));
    }

    /**
     * @return StaticEntityRepository<AppScriptConditionCollection>
     */
    private function createConditionRepository(string ...$conditionIds): StaticEntityRepository
    {
        $conditionRepository = new StaticEntityRepository([]);
        $conditionRepository->addSearch($conditionIds);

        return $conditionRepository;
    }

    private function createAppWithRuleConditions(AppScriptConditionEntity ...$conditions): AppEntity
    {
        $app = AppFixture::createAppEntity('withRuleConditions');
        $app->setScriptConditions(new AppScriptConditionCollection($conditions));

        return $app;
    }

    private function createCondition(string $id, string $identifier): AppScriptConditionEntity
    {
        $condition = new AppScriptConditionEntity();
        $condition->setId($id);
        $condition->setIdentifier($identifier);

        return $condition;
    }

    /**
     * @param list<array<string, mixed>> $payloads
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexPayloadsByIdentifier(array $payloads): array
    {
        $indexed = [];

        foreach ($payloads as $payload) {
            static::assertIsString($payload['identifier']);

            $indexed[$payload['identifier']] = $payload;
        }

        return $indexed;
    }
}
