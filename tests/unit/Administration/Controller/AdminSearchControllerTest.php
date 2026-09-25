<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Administration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopwell\Administration\Controller\AdminSearchController;
use Shopwell\Administration\Service\AdminSearcher;
use Shopwell\Core\Content\Category\CategoryEntity;
use Shopwell\Core\Content\LandingPage\LandingPageDefinition;
use Shopwell\Core\Content\Product\ProductDefinition;
use Shopwell\Core\Content\Product\ProductEntity;
use Shopwell\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopwell\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopwell\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminSearchController::class)]
class AdminSearchControllerTest extends TestCase
{
    private AdminSearchController $controller;

    private Stub&AclCriteriaValidator $criteriaValidator;

    private Stub&DefinitionInstanceRegistry $definitionInstanceRegistry;

    private Stub&DefinitionInstanceRegistry $definitionRegistry;

    private Stub&JsonEntityEncoder $entityEncoder;

    private Stub&RequestCriteriaBuilder $requestCriteriaBuilder;

    private Stub&AdminSearcher $searcher;

    private MockObject&DecoderInterface $serializer;

    protected function setUp(): void
    {
        $this->requestCriteriaBuilder = static::createStub(RequestCriteriaBuilder::class);
        $this->definitionInstanceRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->searcher = static::createStub(AdminSearcher::class);
        $this->serializer = $this->createMock(DecoderInterface::class);
        $this->criteriaValidator = static::createStub(AclCriteriaValidator::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->entityEncoder = static::createStub(JsonEntityEncoder::class);

        $this->controller = $this->getController();
    }

    public function testSearchWithNoQueryReturnsEmptyData(): void
    {
        $this->serializer->expects($this->once())->method('decode')->willReturn([]);

        $response = $this->controller->search(new Request(), Context::createDefaultContext());

        static::assertNotFalse($response->getContent());
        static::assertJsonStringEqualsJsonString('{"data":[]}', $response->getContent());
    }

    public function testSearchWitMissingPrivilegeReturnsViolations(): void
    {
        $this->serializer->expects($this->once())->method('decode')
            ->willReturn(
                [ProductDefinition::class => ['product'], LandingPageDefinition::class => ['page']]
            );

        $this->definitionInstanceRegistry->method('has')
            ->willReturnOnConsecutiveCalls(true, false);

        $validationError = [ProductDefinition::class . ':' . AclRoleDefinition::PRIVILEGE_READ];
        $criteriaValidator = $this->createMock(AclCriteriaValidator::class);
        $criteriaValidator->expects($this->once())->method('validate')
            ->willReturn($validationError);

        $controller = $this->getController(criteriaValidator: $criteriaValidator);

        $response = $controller->search(new Request(['product' => true, 'page' => true]), Context::createDefaultContext());

        static::assertNotFalse($response->getContent());
        $result = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($result);
        static::assertArrayHasKey('data', $result);
        static::assertSame(
            [
                ProductDefinition::class => [
                    'status' => '403',
                    'code' => 'FRAMEWORK__MISSING_PRIVILEGE_ERROR',
                    'title' => 'Forbidden',
                    'detail' => json_encode(['message' => 'Missing privilege', 'missingPrivileges' => $validationError]),
                    'meta' => ['parameters' => []],
                ]],
            $result['data']
        );
    }

    public function testSearchWitMatchingEntitiesReturnsData(): void
    {
        $this->serializer->expects($this->once())->method('decode')
            ->willReturn(
                [ProductEntity::class => ['product'], LandingPageDefinition::class => ['page']]
            );

        $this->definitionInstanceRegistry->method('has')
            ->willReturnOnConsecutiveCalls(true, true);

        $productEntity = new ProductEntity();
        $productEntity->setUniqueIdentifier(Uuid::randomHex());

        $collection = new EntityCollection([$productEntity]);

        $searcher = $this->createMock(AdminSearcher::class);
        $searcher->expects($this->once())->method('search')
            ->willReturn([
                ProductEntity::class => [
                    'data' => $collection,
                    'total' => \count($collection),
                ],
                CategoryEntity::class => [
                    'data' => $collection,
                    'total' => \count($collection),
                ],
            ]);

        $controller = $this->getController(searcher: $searcher);

        $response = $controller->search(new Request(['product' => true, 'page' => true]), Context::createDefaultContext());

        static::assertNotFalse($response->getContent());
        $result = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($result);
        static::assertArrayHasKey('data', $result);
        static::assertArrayHasKey(ProductEntity::class, $result['data']);
        static::assertArrayHasKey(CategoryEntity::class, $result['data']);
    }

    private function getController(
        ?AdminSearcher $searcher = null,
        ?AclCriteriaValidator $criteriaValidator = null
    ): AdminSearchController {
        return new AdminSearchController(
            $this->requestCriteriaBuilder,
            $this->definitionInstanceRegistry,
            $searcher ?? $this->searcher,
            $this->serializer,
            $criteriaValidator ?? $this->criteriaValidator,
            $this->definitionRegistry,
            $this->entityEncoder
        );
    }
}
