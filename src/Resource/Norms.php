<?php

declare(strict_types=1);

namespace Klaracase\Resource;

use Klaracase\Http\Path;
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
     */
    public function get(string $reference, ?string $jurisdiction = null): Norm
    {
        [$body, $meta] = $this->transport->send('GET', '/norms/' . Path::segment($reference), [
            'jurisdiction' => $jurisdiction,
        ]);

        return new Norm($body, $meta);
    }
}
