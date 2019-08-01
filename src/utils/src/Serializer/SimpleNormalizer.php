<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace Hyperf\Utils\Serializer;

use Hyperf\Contract\NormalizerInterface;

class SimpleNormalizer implements NormalizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function normalize($object)
    {
        return $object;
    }

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, $class)
    {
        if ($class[0] == '?') {
            $class = substr($class, 1);
        }
        switch ($class) {
            case 'int':
                return (int) $data;
            case 'string':
                return (string) $data;
            case 'float':
                return (float) $data;
            case 'array':
                return (array) $data;
            case 'bool':
                return (bool) $data;
            default:
                return $data;
        }
    }
}
