<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Flow\Dispatching\Storer;

use Shopwell\Core\Content\Flow\Dispatching\Aware\CustomAppAware;
use Shopwell\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopwell\Core\Content\Flow\Dispatching\StorableFlow;
use Shopwell\Core\Framework\Event\FlowEventAware;
use Shopwell\Core\Framework\Log\Package;

#[Package('after-sales')]
class CustomAppStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!($event instanceof CustomAppAware) || isset($stored[CustomAppAware::CUSTOM_DATA])) {
            return $stored;
        }

        $customAppData = $event->getCustomAppData();
        if ($customAppData === null || $customAppData === []) {
            return $stored;
        }

        foreach ($customAppData as $key => $data) {
            $stored[ScalarValuesAware::STORE_VALUES][$key] = $data;
            $stored[$key] = $data;
        }

        return $stored;
    }

    /**
     * @codeCoverageIgnore
     */
    public function restore(StorableFlow $storable): void
    {
    }
}
