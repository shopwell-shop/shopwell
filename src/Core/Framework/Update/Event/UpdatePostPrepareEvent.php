<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Update\Event;

use Shopwell\Core\Framework\Context;
use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class UpdatePostPrepareEvent extends UpdateEvent
{
    public function __construct(
        Context $context,
        private readonly string $currentVersion,
        private readonly string $newVersion
    ) {
        parent::__construct($context);
    }

    public function getCurrentVersion(): string
    {
        return $this->currentVersion;
    }

    public function getNewVersion(): string
    {
        return $this->newVersion;
    }
}
