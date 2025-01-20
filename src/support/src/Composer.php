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

namespace Hyperf\Support;

use Composer\Autoload\ClassLoader;
use Hyperf\Collection\Collection;
use RuntimeException;

use function Hyperf\Collection\collect;

class Composer
{
    private static ?Collection $content = null;

    private static ?Collection $json = null;

    private static array $extra = [];

    private static array $scripts = [];

    private static array $versions = [];

    private static ?ClassLoader $classLoader = null;

    /**
     * @throws RuntimeException When `composer.lock` does not exist.
     */
    public static function getLockContent(): Collection
    {
        if (self::$content) {
            return self::$content;
        }

        if (! $path = self::discoverLockFile()) {
            throw new RuntimeException('composer.lock not found.');
        }

        self::$content = collect(json_decode(file_get_contents($path), true));
        $packages = self::$content->offsetGet('packages') ?? [];
        $packagesDev = self::$content->offsetGet('packages-dev') ?? [];

        foreach (array_merge($packages, $packagesDev) as $package) {
            $packageName = $package['name'] ?? '';
            if (! $packageName) {
                continue;
            }

            foreach ($package as $key => $value) {
                match ($key) {
                    'extra' => self::$extra[$packageName] = $value,
                    'scripts' => self::$scripts[$packageName] = $value,
                    'version' => self::$versions[$packageName] = $value,
                    default => null,
                };
            }
        }

        return self::$content;
    }

    public static function getJsonContent(): Collection
    {
        if (self::$json) {
            return self::$json;
        }

        if (! is_readable($path = BASE_PATH . '/composer.json')) {
            throw new RuntimeException('composer.json is not readable.');
        }

        return self::$json = collect(json_decode(file_get_contents($path), true));
    }

    public static function discoverLockFile(): string
    {
        if (is_readable($path = BASE_PATH . '/composer.lock')) {
            return $path;
        }

        return '';
    }

    public static function getMergedExtra(?string $key = null): array
    {
        if (! self::$extra) {
            self::getLockContent();
        }

        if ($key === null) {
            return self::$extra;
        }

        $extra = [];

        foreach (self::$extra as $config) {
            if (! isset($config[$key])) {
                continue;
            }

            foreach ($config[$key] as $k => $v) {
                if (is_array($v)) {
                    $extra[$k] = array_merge($extra[$k] ?? [], $v);
                } else {
                    $extra[$k][] = $v;
                }
            }
        }

        return $extra;
    }

    public static function getLoader(): ClassLoader
    {
        return self::$classLoader ??= self::findLoader();
    }

    public static function setLoader(ClassLoader $classLoader): ClassLoader
    {
        return self::$classLoader = $classLoader;
    }

    public static function getScripts(): array
    {
        if (! self::$scripts) {
            self::getLockContent();
        }

        return self::$scripts;
    }

    public static function getVersions(): array
    {
        if (! self::$versions) {
            self::getLockContent();
        }

        return self::$versions;
    }

    public static function hasPackage(string $packageName): bool
    {
        if (! self::$json) {
            self::getJsonContent();
        }

        if (self::$json['require'][$packageName] ?? self::$json['require-dev'][$packageName] ?? self::$json['replace'][$packageName] ?? '') {
            return true;
        }

        if (! self::$versions) {
            self::getLockContent();
        }

        return isset(self::$versions[$packageName]);
    }

    private static function findLoader(): ClassLoader
    {
        $loaders = spl_autoload_functions();

        foreach ($loaders as $loader) {
            if (is_array($loader) && $loader[0] instanceof ClassLoader) {
                return $loader[0];
            }
        }

        throw new RuntimeException('Composer loader not found.');
    }
}
