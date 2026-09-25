<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Event;

use Shopwell\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Framework\App\AppEntity;
use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopwell\Core\Framework\Webhook\Hookable;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class MediaUploadedEvent extends Event implements ScalarValuesAware, FlowEventAware, Hookable
{
    public const EVENT_NAME = 'media.uploaded';

    public function __construct(
        private readonly string $mediaId,
        private readonly Context $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(FlowMailVariables::MEDIA_ID, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getValues(): array
    {
        return [
            FlowMailVariables::MEDIA_ID => $this->mediaId,
        ];
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [
            'mediaId' => $this->mediaId,
        ];
    }

    public function isAllowed(string $appId, AclPrivilegeCollection $permissions): bool
    {
        return $permissions->isAllowed('media', 'read');
    }
}
