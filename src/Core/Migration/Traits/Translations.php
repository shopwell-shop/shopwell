<?php declare(strict_types=1);

namespace Shopwell\Core\Migration\Traits;

use Shopwell\Core\Framework\Log\Package;

#[Package('framework')]
class Translations
{
    /**
     * @param array<string, string|null> $zhCn
     * @param array<string, string|null> $english
     */
    public function __construct(
        protected array $zhCn,
        protected array $english
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function getZhCn(): array
    {
        return $this->zhCn;
    }

    /**
     * @return array<string, string|null>
     */
    public function getEnglish(): array
    {
        return $this->english;
    }

    /**
     * @return list<string>
     */
    public function getColumns(): array
    {
        return array_keys($this->english);
    }
}
