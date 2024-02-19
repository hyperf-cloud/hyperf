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
namespace Hyperf\Cache\Collector;

use Carbon\Carbon;
use Hyperf\Coordinator\Constants;
use Hyperf\Coordinator\CoordinatorManager;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Stringable\Str;

final class Memory
{
    /**
     * @var array<string, null|Carbon>
     */
    private array $keys = [];

    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    private ?int $loopCid = null;

    private ?int $waitCloseCid = null;

    private bool $stopped = false;

    public static function instance(): static
    {
        static $instance;

        return $instance ??= new self();
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, ?Carbon $ttl = null): bool
    {
        $this->loop();
        $this->keys[$key] = $ttl;
        $this->values[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->keys[$key], $this->values[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->keys = [];
        $this->values = [];

        return true;
    }

    public function clearPrefix(string $prefix): bool
    {
        foreach ($this->keys as $key => $ttl) {
            if (Str::startsWith($key, $prefix)) {
                $this->delete($key);
            }
        }

        return true;
    }

    public function stop(): void
    {
        $this->stopped = true;
    }

    private function loop(): void
    {
        $this->loopCid ??= Coroutine::create(function () {
            while (! $this->stopped) {
                foreach ($this->keys as $key => $ttl) {
                    if ($ttl instanceof Carbon && Carbon::now()->gt($ttl)) {
                        $this->delete($key);
                    }
                }
                sleep(1);
            }
            $this->loopCid = null;
        });

        $this->waitCloseCid ??= Coroutine::create(function () {
            CoordinatorManager::until(Constants::WORKER_EXIT)->yield();
            $this->stop();
            $this->clear();
            $this->waitCloseCid = null;
        });
    }
}
