<?php declare(strict_types=1);

namespace Shopwell\Core\Content\MailTemplate\Aggregate\MailTemplateMedia;

use Shopwell\Core\Content\Media\MediaCollection;
use Shopwell\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopwell\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MailTemplateMediaEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class MailTemplateMediaCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getMailTemplateIds(): array
    {
        return $this->fmap(static fn (MailTemplateMediaEntity $mailTemplateAttachment) => $mailTemplateAttachment->getMailTemplateId());
    }

    public function filterByMailTemplateId(string $id): self
    {
        return $this->filter(static fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMailTemplateId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(static fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(static fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMediaId() === $id);
    }

    public function getMedia(): MediaCollection
    {
        return new MediaCollection(
            $this->fmap(static fn (MailTemplateMediaEntity $mailTemplateMedia) => $mailTemplateMedia->getMedia())
        );
    }

    public function getApiAlias(): string
    {
        return 'mail_template_media_collection';
    }

    protected function getExpectedClass(): string
    {
        return MailTemplateMediaEntity::class;
    }
}
