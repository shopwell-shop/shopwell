<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Framework\Event\EventData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopwell\Core\Framework\Event\EventData\ObjectType;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ObjectType::class)]
class ObjectTypeTest extends TestCase
{
    public function testToArray(): void
    {
        $expected = [
            'type' => 'object',
            'data' => [
                'myBool' => [
                    'type' => 'bool',
                ],
                'myString' => [
                    'type' => 'string',
                ],
            ],
        ];

        static::assertSame(
            $expected,
            (new ObjectType())
                ->add('myBool', new ScalarValueType(ScalarValueType::TYPE_BOOL))
                ->add('myString', new ScalarValueType(ScalarValueType::TYPE_STRING))
                ->toArray()
        );
    }
}
