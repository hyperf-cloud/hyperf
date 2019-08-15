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

namespace Hyperf\Validation;

use Hyperf\Database\ConnectionResolverInterface;
use Hyperf\Translation\Contracts\Translator;
use Psr\Container\ContainerInterface;

class ValidatorFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $translator = $container->get(Translator::class);

        $validator = make(Factory::class, compact('translator', 'container'));

        if ($container->has(ConnectionResolverInterface::class) && $container->has(PresenceVerifierInterface::class)) {
            $presenceVerifier = $container->get(PresenceVerifierInterface::class);
            $validator->setPresenceVerifier($presenceVerifier);
        }

        return $validator;
    }
}
