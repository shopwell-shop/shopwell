<?php declare(strict_types=1);

namespace Shopwell\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Event\EventData\EventDataCollection;
use Shopwell\Core\Framework\Event\EventData\ScalarValueType;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Event\LanguageAware;
use Shopwell\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class LanguageAwareEvent implements FlowEventAware, LanguageAware
{
    public function __construct(private readonly ?string $languageId)
    {
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('languageId', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }
}
