<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Http\Request;
use Klaracase\Http\Response;
use Klaracase\Http\Transport;
use Klaracase\RequestOptions;

/**
 * @internal
 */
abstract class AbstractResource
{
    public function __construct(protected readonly Transport $transport)
    {
    }

    protected function call(Request $request, ?RequestOptions $options): Response
    {
        return $this->transport->send($request, $options ?? new RequestOptions());
    }
}
