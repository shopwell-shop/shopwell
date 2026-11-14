<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Api\WorkflowController;
use Shopwell\Core\Framework\Workflow\Definition\Codec\WorkflowDocumentCodec;
use Shopwell\Core\Framework\Workflow\Definition\Type\Loader\BuiltInWorkflowNodeTypeLoader;
use Shopwell\Core\Framework\Workflow\Definition\Type\Registry\WorkflowNodeTypeRegistry;
use Shopwell\Core\Framework\Workflow\Definition\Type\Resolver\WorkflowNodeTypeResolver;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\StructuredWorkflowGraphPolicy;
use Shopwell\Core\Framework\Workflow\Diagnostics\Policy\WorkflowGraphPolicyRegistry;
use Shopwell\Core\Framework\Workflow\Diagnostics\WorkflowGraphDiagnostics;
use Shopwell\Core\Framework\Workflow\WorkflowException;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowController::class)]
class WorkflowControllerTest extends TestCase
{
    private WorkflowController $controller;

    protected function setUp(): void
    {
        $registry = new WorkflowNodeTypeRegistry([new BuiltInWorkflowNodeTypeLoader()]);
        $this->controller = new WorkflowController($registry, new WorkflowDocumentCodec(Validation::createValidator()), new WorkflowGraphDiagnostics(new WorkflowNodeTypeResolver($registry, new EventDispatcher()), new WorkflowGraphPolicyRegistry([new StructuredWorkflowGraphPolicy()]), new EventDispatcher()));
    }

    public function testCatalogExposesSerializableTypeSpecifications(): void
    {
        $data = json_decode((string) $this->controller->types()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame('core:start', $data['types'][0]['name']);
        static::assertSame('start', $data['types'][0]['kind']);
        static::assertSame(['out'], $data['types'][0]['outputPorts']);
    }

    public function testDiagnosesAnIncompleteDraftWithoutPersistingIt(): void
    {
        $request = new Request(content: '{"schemaVersion":1,"policy":"core:structured","nodes":[],"connections":[]}');

        $data = json_decode((string) $this->controller->diagnose($request)->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertSame(['start_count', 'end_missing'], array_column($data['violations'], 'code'));
    }

    public function testMalformedPayloadIsRejectedAtTheBoundary(): void
    {
        $this->expectException(WorkflowException::class);

        $this->controller->diagnose(new Request(content: '{'));
    }
}
