<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Update\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class UpdatePreFinishEvent extends UpdateEvent
{
    public function __construct(
        Context $context,
        private readonly string $oldVersion,
        private readonly string $newVersion
    ) {
        parent::__construct($context);
    }

    public function getOldVersion(): string
    {
        return $this->oldVersion;
    }

    public function getNewVersion(): string
    {
        return $this->newVersion;
    }
}
