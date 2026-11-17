<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Workflow\Execution\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopwell\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopwell\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Workflow\Execution\Api\WorkflowPayloadProtection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WorkflowPayloadProtection::class)]
class WorkflowPayloadProtectionTest extends TestCase
{
    public function testPrivatePayloadsRemainInternalWhilePublicFieldsStayReadable(): void
    {
        $payload = new JsonField('payload', 'payload');
        $name = new StringField('name', 'name');
        $fields = new FieldCollection([$payload, $name]);
        $protection = new WorkflowPayloadProtection('workflow_log', ['payload', 'missing']);

        $protection->modifyFields($fields);

        static::assertSame('workflow_log', $protection->getEntityName());
        static::assertFalse($payload->is(ApiAware::class));
        static::assertTrue($name->is(ApiAware::class));
        static::assertCount(2, $fields);
    }
}
