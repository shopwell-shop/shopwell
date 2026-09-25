<?php declare(strict_types=1);

namespace Shopwell\Core\Content\Media\Message;

use Shopwell\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class UpdateThumbnailsMessage extends GenerateThumbnailsMessage
{
    private bool $strict = false;

    private bool $force = false;

    public function isStrict(): bool
    {
        return $this->strict;
    }

    public function setStrict(bool $isStrict): void
    {
        $this->strict = $isStrict;
    }

    public function isForce(): bool
    {
        return $this->force;
    }

    public function setForce(bool $isForce): void
    {
        $this->force = $isForce;
    }
}
