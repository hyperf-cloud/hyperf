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

namespace Hyperf\Database\Model;

use Hyperf\Collection\Arr;
use RuntimeException;

/**
 * @template TModel of Model
 */
class ModelNotFoundException extends RuntimeException
{
    /**
     * Name of the affected Model model.
     * @var null|class-string<TModel>
     */
    protected ?string $model = null;

    /**
     * The affected model IDs.
     * @var array<int,int|string>
     */
    protected array $ids = [];

    /**
     * Set the affected Model model and instance ids.
     *
     * @param array|int|string $ids
     * @return $this
     */
    public function setModel(string $model, $ids = [])
    {
        $this->model = $model;
        $this->ids = Arr::wrap($ids);

        $this->message = "No query results for model [{$model}]";

        if (count($this->ids) > 0) {
            $this->message .= ' ' . implode(', ', $this->ids);
        } else {
            $this->message .= '.';
        }

        return $this;
    }

    /**
     * Get the affected Model model.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Get the affected Model model IDs.
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
