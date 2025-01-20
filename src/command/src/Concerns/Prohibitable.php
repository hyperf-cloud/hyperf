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

namespace Hyperf\Command\Concerns;

trait Prohibitable
{
    /**
     * Indicates if the command should be prohibited from running.
     *
     * @var bool
     */
    protected static $prohibitedFromRunning = false;

    /**
     * Indicate whether the command should be prohibited from running.
     *
     * @param bool $prohibit
     */
    public static function prohibit($prohibit = true)
    {
        static::$prohibitedFromRunning = $prohibit;
    }

    /**
     * Determine if the command is prohibited from running and display a warning if so.
     *
     * @return bool
     */
    protected function isProhibited(bool $quiet = false)
    {
        if (! static::$prohibitedFromRunning) {
            return false;
        }

        if (! $quiet) {
            $this->output->warn('This command is prohibited from running in this environment.');
        }

        return true;
    }
}
