<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\Traits;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class MailSubjectUpdate
{
    public function __construct(
        protected string $type,
        protected ?string $enSubject = null,
        protected ?string $zhSubject = null
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getZhSubject(): ?string
    {
        return $this->zhSubject;
    }

    public function getEnSubject(): ?string
    {
        return $this->enSubject;
    }
}
