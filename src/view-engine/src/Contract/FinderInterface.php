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
namespace Hyperf\ViewEngine\Contract;

interface FinderInterface
{
    /**
     * Hint path delimiter value.
     *
     * @var string
     */
    public const HINT_PATH_DELIMITER = '::';

    /**
     * Get the fully qualified location of the view.
     *
     * @return string
     */
    public function find(string $view);

    /**
     * Add a location to the finder.
     */
    public function addLocation(string $location);

    /**
     * Add a namespace hint to the finder.
     */
    public function addNamespace(string $namespace, array|string $hints);

    /**
     * Prepend a namespace hint to the finder.
     */
    public function prependNamespace(string $namespace, array|string $hints);

    /**
     * Replace the namespace hints for the given namespace.
     */
    public function replaceNamespace(string $namespace, array|string $hints);

    /**
     * Add a valid view extension to the finder.
     */
    public function addExtension(string $extension);

    /**
     * Flush the cache of located views.
     */
    public function flush();
}
