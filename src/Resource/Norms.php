<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Exception\KlaracaseException;
use Klaracase\Http\Path;
use Klaracase\Http\Request;
use Klaracase\RequestOptions;
use Klaracase\Response\Norm;

/**
 * Statute norms.
 */
final class Norms extends AbstractResource
{
    /**
     * Read the current consolidated text of one norm. Costs 1 credit.
     *
     * @param string $reference For example `§ 535 BGB` or `Art. 6 DSGVO`, at most 120 characters.
     * @param string|null $jurisdiction DE or EU. Read only when the abbreviation is ambiguous.
     * @param RequestOptions|null $options Timeout, retries, extra headers and query for this call.
     *
     * @throws KlaracaseException On an API error or when no answer arrives.
     */
    public function get(string $reference, ?string $jurisdiction = null, ?RequestOptions $options = null): Norm
    {
        $response = $this->call(new Request('GET', '/norms/' . Path::segment($reference), [
            'jurisdiction' => $jurisdiction,
        ]), $options);

        return Norm::fromArray($response->body, $response->meta);
    }
}
