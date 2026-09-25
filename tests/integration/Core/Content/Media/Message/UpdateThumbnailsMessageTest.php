<?php declare(strict_types=1);

namespace Shopwell\Tests\Integration\Core\Content\Media\Message;

use PHPUnit\Framework\TestCase;
use Shopwell\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @internal
 */
#[Package('discovery')]
class UpdateThumbnailsMessageTest extends TestCase
{
    use KernelTestBehaviour;

    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->serializer = static::getContainer()->get('serializer');
    }

    public function testDeserializationWithStrict(): void
    {
        $message = new UpdateThumbnailsMessage();
        $message->setStrict(true);

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertTrue($deserialized->isStrict());
    }

    public function testDeserializationDefaultsToNonStrict(): void
    {
        $message = new UpdateThumbnailsMessage();

        $serialized = $this->serializer->serialize($message, 'json');
        $deserialized = $this->serializer->deserialize($serialized, UpdateThumbnailsMessage::class, 'json');

        static::assertInstanceOf(UpdateThumbnailsMessage::class, $deserialized);
        static::assertFalse($deserialized->isStrict());
    }
}
