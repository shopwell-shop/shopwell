<?php declare(strict_types=1);

namespace Shopwell\Tests\Migration;

/**
 * @internal
 */
class Translations
{
    private ?string $enPlain = null;

    private ?string $enHtml = null;

    private ?string $zhPlain = null;

    private ?string $zhHtml = null;

    public function getEnPlain(): ?string
    {
        return $this->enPlain;
    }

    public function getEnHtml(): ?string
    {
        return $this->enHtml;
    }

    public function getZhPlain(): ?string
    {
        return $this->zhPlain;
    }

    public function getZhHtml(): ?string
    {
        return $this->zhHtml;
    }

    public function setEnPlain(string $enPlain): void
    {
        $this->enPlain = $enPlain;
    }

    public function setEnHtml(string $enHtml): void
    {
        $this->enHtml = $enHtml;
    }

    public function setZhPlain(string $zhPlain): void
    {
        $this->zhPlain = $zhPlain;
    }

    public function setZhHtml(string $zhHtml): void
    {
        $this->zhHtml = $zhHtml;
    }
}
