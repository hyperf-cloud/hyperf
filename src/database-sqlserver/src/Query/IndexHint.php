<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Database\Sqlsrv\Query;

class IndexHint
{
    /**
     * The type of query hint.
     */
    public string $type;

    /**
     * The name of the index.
     */
    public string $index;

    /**
     * Create a new index hint instance.
     */
    public function __construct(string $type, string $index)
    {
        $this->type = $type;
        $this->index = $index;
    }
}
