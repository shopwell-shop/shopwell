<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\App\Validation\Error;

use Shopwell\Core\Framework\Log\Package;
use Shopwell\Core\Framework\Struct\Collection;

/**
 * @internal only for use by the app-system
 *
 * @extends Collection<Error>
 */
#[Package('framework')]
class ErrorCollection extends Collection
{
    /**
     * @param Error $error
     */
    public function add($error): void
    {
        $this->set($error->getMessageKey(), $error);
    }

    public function addErrors(ErrorCollection $errors): void
    {
        foreach ($errors as $error) {
            $this->set($error->getMessageKey(), $error);
        }
    }

    protected function getExpectedClass(): ?string
    {
        return Error::class;
    }
}
