<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\Traits;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

#[Package('framework')]
class MailUpdate
{
    public function __construct(
        protected string $type,
        protected ?string $enPlain = null,
        protected ?string $enHtml = null,
        protected ?string $zhPlain = null,
        protected ?string $zhHtml = null
    ) {
    }

    public function loadByDirectoryName(string $directoryName): void
    {
        $filesystem = new Filesystem();
        $path = __DIR__ . '/../Fixtures/mails/' . $directoryName;

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

    public function getEnPlain(): ?string
    {
        return $this->enPlain;
    }

    public function setEnPlain(?string $enPlain): void
    {
        $this->enPlain = $enPlain;
    }

    public function getEnHtml(): ?string
    {
        return $this->enHtml;
    }

    public function setEnHtml(?string $enHtml): void
    {
        $this->enHtml = $enHtml;
    }

    public function getZhPlain(): ?string
    {
        return $this->zhPlain;
    }

    public function setZhPlain(?string $zhPlain): void
    {
        $this->zhPlain = $zhPlain;
    }

    public function getZhHtml(): ?string
    {
        return $this->zhHtml;
    }

    public function setZhHtml(?string $zhHtml): void
    {
        $this->zhHtml = $zhHtml;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
