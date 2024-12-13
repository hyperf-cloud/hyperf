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

namespace Hyperf\Elasticsearch;

use Elasticsearch\ClientBuilder;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Guzzle\RingPHP\CoroutineHandler;
use RuntimeException;

class ClientBuilderFactory
{
    public function __construct(protected ClientFactory $guzzleClientFactory)
    {
    }

    /**
     * @return ClientBuilder|\Elastic\Elasticsearch\ClientBuilder
     */
    public function create() // @phpstan-ignore class.notFound
    {
        if (class_exists('Elastic\Elasticsearch\ClientBuilder')) {
            $builder = \Elastic\Elasticsearch\ClientBuilder::create();
            $builder->setHttpClient(
                $this->guzzleClientFactory->create()
            );

            return $builder;
        }

        if (class_exists('Elasticsearch\ClientBuilder')) {
            $builder = ClientBuilder::create();
            if (Coroutine::inCoroutine()) {
                $builder->setHandler(new CoroutineHandler());
            }

            return $builder;
        }

        // Will not be here
        throw new RuntimeException('The "elasticsearch/elasticsearch" package is required to use ClientBuilder, Please run "composer require elasticsearch/elasticsearch".');
    }
}
