<?php

declare(strict_types=1);

namespace Vanta\Integration\Symfony\Temporal;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Vanta\Integration\Symfony\Temporal\DependencyInjection\Compiler\ClientCompilerPass;
use Vanta\Integration\Symfony\Temporal\DependencyInjection\Compiler\DoctrineCompilerPass;
use Vanta\Integration\Symfony\Temporal\DependencyInjection\Compiler\WorkflowCompilerPass;

final class TemporalBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new WorkflowCompilerPass());
        $container->addCompilerPass(new ClientCompilerPass());
        $container->addCompilerPass(new DoctrineCompilerPass());
    }
}