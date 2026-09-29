<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\Structs;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
class MailTemplateCreateStruct
{
    protected string $enHtml;

    protected string $enPlain;

    protected string $zhHtml;

    protected string $zhPlain;

    public function __construct(
        protected string $mailTemplateFixtureDirectoryName,
        protected string $enSubject,
        protected string $zhSubject,
        protected string $enDescription,
        protected string $zhDescription,
        protected string $enSenderName,
        protected string $zhSenderName,
        protected bool $isSystemDefault = true,
    ) {
        $filesystem = new Filesystem();
        $path = __DIR__ . '/../Fixtures/mails/' . $this->mailTemplateFixtureDirectoryName;

        $this->enHtml = $filesystem->readFile($path . '/en-html.html.twig');
        $this->zhHtml = $filesystem->readFile($path . '/zh-html.html.twig');

        if ($filesystem->exists($path . '/en-plain.txt.twig')) {
            $this->enPlain = $filesystem->readFile($path . '/en-plain.txt.twig');
        } else {
            $this->enPlain = $filesystem->readFile($path . '/en-plain.html.twig');
        }

        if ($filesystem->exists($path . '/zh-plain.txt.twig')) {
            $this->zhPlain = $filesystem->readFile($path . '/zh-plain.txt.twig');
        } else {
            $this->zhPlain = $filesystem->readFile($path . '/zh-plain.html.twig');
        }
    }

    public function getEnHtml(): string
    {
        return $this->enHtml;
    }

    public function getEnPlain(): string
    {
        return $this->enPlain;
    }

    public function getZhHtml(): string
    {
        return $this->zhHtml;
    }

    public function getZhPlain(): string
    {
        return $this->zhPlain;
    }

    public function getEnSubject(): string
    {
        return $this->enSubject;
    }

    public function getZhSubject(): string
    {
        return $this->zhSubject;
    }

    public function getEnDescription(): string
    {
        return $this->enDescription;
    }

    public function getZhDescription(): string
    {
        return $this->zhDescription;
    }

    public function getEnSenderName(): string
    {
        return $this->enSenderName;
    }

    public function getZhSenderName(): string
    {
        return $this->zhSenderName;
    }

    public function isSystemDefault(): bool
    {
        return $this->isSystemDefault;
    }
}
