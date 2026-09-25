<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\DataAbstractionLayer\Write\FieldException;

use Shopwell\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
class ExpectedArrayException extends DataAbstractionLayerException implements WriteFieldException
{
    public function __construct(string $path)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__WRITE_MALFORMED_INPUT',
            'Expected data at {{ path }} to be an array.',
            ['path' => $path]
        );
    }

    public function getPath(): string
    {
        return $this->getParameters()['path'];
    }
}
