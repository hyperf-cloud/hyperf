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
namespace Hyperf\GraphQL;

use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\GraphQL\Annotation\Mutation;
use Hyperf\GraphQL\Annotation\Query;
use Psr\Container\ContainerInterface;
use TheCodingMachine\GraphQLite\Mappers\RecursiveTypeMapperInterface;
use TheCodingMachine\GraphQLite\QueryField;
use TheCodingMachine\GraphQLite\QueryProviderInterface;

class QueryProvider implements QueryProviderInterface
{
    public function __construct(private FieldsBuilderFactory $fieldsBuilderFactory, private RecursiveTypeMapperInterface $recursiveTypeMapper, private ContainerInterface $container)
    {
    }

    /**
     * @return QueryField[]
     */
    public function getQueries(): array
    {
        $queryList = [];
        $classes = AnnotationCollector::getMethodsByAnnotation(Query::class);
        $classes = array_unique(array_column($classes, 'class'));
        foreach ($classes as $className) {
            $fieldsBuilder = $this->fieldsBuilderFactory->buildFieldsBuilder($this->recursiveTypeMapper);
            $queryList = array_merge($queryList, $fieldsBuilder->getQueries($this->container->get($className)));
        }
        return $queryList;
    }

    /**
     * @return QueryField[]
     */
    public function getMutations(): array
    {
        $mutationList = [];
        $classes = AnnotationCollector::getMethodsByAnnotation(Mutation::class);
        $classes = array_unique(array_column($classes, 'class'));
        foreach ($classes as $className) {
            $fieldsBuilder = $this->fieldsBuilderFactory->buildFieldsBuilder($this->recursiveTypeMapper);
            $mutationList = array_merge($mutationList, $fieldsBuilder->getMutations($this->container->get($className)));
        }
        return $mutationList;
    }
}
