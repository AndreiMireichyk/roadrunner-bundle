<?php

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Baldinof\RoadRunnerBundle\Http\Middleware\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Psr\Http\Server\MiddlewareInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class MiddlewareCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(MiddlewareStack::class)) {
            return;
        }

        $stack = $container->getDefinition(MiddlewareStack::class);

        $middlewares = $container->getParameter('baldinof_road_runner.middlewares');
        $defaultMiddlewares = $container->getParameter('baldinof_road_runner.middlewares.default');

        $middlewaresToRemove = [];
        if (!$container->hasDefinition('session')) {
            $middlewaresToRemove[] = NativeSessionMiddleware::class;
        }

        $beforeMiddlewares = array_diff($defaultMiddlewares['before'], $middlewaresToRemove);
        $afterMiddlewares = array_diff($defaultMiddlewares['after'], $middlewaresToRemove);

        foreach (array_merge($beforeMiddlewares, $middlewares, $afterMiddlewares) as $m) {
            if (!$container->has($m)) {
                throw new LogicException("No service found for middleware '$m'.");
            }

            $definition = $container->findDefinition($m);
            $class = $definition->getClass();

            if (null === $class) {
                throw new InvalidArgumentException("Missing class definition for service '$m'.");
            }

            if (!is_a($class, MiddlewareInterface::class, true) && !is_a($class, IteratorMiddlewareInterface::class, true)) {
                throw new InvalidArgumentException(sprintf("Service '%s' should implements '%s' or '%s'.", $m, MiddlewareInterface::class, IteratorMiddlewareInterface::class));
            }

            $stack->addMethodCall('pipe', [new Reference($m)]);
        }
    }
}
