<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Http\Transport;

/**
 * @internal
 */
abstract class AbstractResource
{
    public function __construct(protected readonly Transport $transport)
    {
    }
}
