<?php declare(strict_types=1);

namespace Shopwell\Core\Framework\Validation\Constraint;

use Shopwell\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

#[Package('framework')]
class ArrayOfType extends Constraint
{
    final public const INVALID_MESSAGE = 'This value "{{ value }}" should be of type {{ type }}.';
    final public const INVALID_TYPE_MESSAGE = 'This value should be of type array.';

    public function __construct(public string $type)
    {
        parent::__construct();
    }
}
