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

namespace HyperfTest\Coroutine;

use Hyperf\Coroutine\Mutex;
use Hyperf\Coroutine\WaitGroup;
use Hyperf\Engine\Channel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function Hyperf\Coroutine\go;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class MutexTest extends TestCase
{
    public function testMutexLock()
    {
        $chan = new Channel(5);
        $func = function (string $value) use ($chan) {
            if (Mutex::lock('test')) {
                try {
                    usleep(1000);
                    $chan->push($value);
                } finally {
                    Mutex::unlock('test');
                }
            }
        };

        $wg = new WaitGroup(5);
        foreach (['h', 'e', 'l', 'l', 'o'] as $value) {
            go(function () use ($func, $value, $wg) {
                $func($value);
                $wg->done();
            });
        }

        $res = '';
        $wg->wait(1);
        for ($i = 0; $i < 5; ++$i) {
            $res .= $chan->pop(1);
        }

        $this->assertSame('hello', $res);
    }
}
