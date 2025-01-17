<?php
/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2023, The Vanta
 */

declare(strict_types=1);

namespace Vanta\Integration\Symfony\Temporal\Test\Functional;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;

use PHPUnit\Framework\Attributes\CoversClass;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelInterface as Kernel;
use Vanta\Integration\Symfony\Temporal\DependencyInjection\Compiler\DoctrineCompilerPass;
use Vanta\Integration\Symfony\Temporal\InstalledVersions;
use Vanta\Integration\Symfony\Temporal\TemporalBundle;

#[CoversClass(DoctrineCompilerPass::class)]
final class DoctrineTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<string, string> $options
     */
    protected static function createKernel(array $options = []): Kernel
    {
        /**
         * @var TestKernel $kernel
         */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(TemporalBundle::class);
        $kernel->handleOptions($options);

        return $kernel;
    }


    public function testRegisterDoctrineClearEntityManagerFinalizer(): void
    {
        InstalledVersions::setHandler(static function (string $package, string $class, array $parentPackages): bool {
            return $package == 'doctrine/doctrine-bundle';
        });

        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestBundle(DoctrineBundle::class);
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/doctrine.yaml');


            $kernel->addTestCompilerPass(new class() implements CompilerPass {
                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->hasDefinition('temporal.doctrine_clear_entity_manager.finalizer'));
                }
            });
        }]);
    }


    /**
     * @param non-empty-string $id
     * @param array{0: Reference, 1: non-empty-string}  $arguments
     */
    #[DataProvider('registerDoctrinePingFinalizersDataProvider')]
    public function testRegisterDoctrinePingFinalizers(string $id, array $arguments): void
    {
        InstalledVersions::setHandler(static function (string $package, string $class, array $parentPackages): bool {
            return $package == 'doctrine/doctrine-bundle';
        });


        self::bootKernel(['config' => static function (TestKernel $kernel) use ($id, $arguments): void {
            $kernel->addTestBundle(DoctrineBundle::class);
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/doctrine.yaml');


            $kernel->addTestCompilerPass(new class($id, $arguments) implements CompilerPass {
                /**
                 * @param non-empty-string $id
                 * @param array{0: Reference, 1: non-empty-string}  $arguments
                 */
                public function __construct(
                    private readonly string $id,
                    private readonly array $arguments,
                ) {
                }


                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->hasDefinition($this->id));
                    assertEquals($this->arguments, $container->getDefinition($this->id)->getArguments());
                }
            });
        }]);
    }


    /**
     * @return iterable<array{0: non-empty-string, 1: array{0: Reference, 1: non-empty-string}}>
     */
    public static function registerDoctrinePingFinalizersDataProvider(): iterable
    {
        yield ['temporal.doctrine_ping_connection_default.finalizer', [new Reference('doctrine'), 'default']];
        yield ['temporal.doctrine_ping_connection_customer.finalizer', [new Reference('doctrine'), 'customer']];
    }


    /**
     * @param non-empty-string                $id
     * @param non-empty-string                $decoratedInspectorId
     * @param non-empty-array<int, Reference> $arguments
     */
    #[DataProvider('decorateInspectorDataProvider')]
    public function testDecorateInspector(string $id, string $decoratedInspectorId, array $arguments): void
    {
        InstalledVersions::setHandler(static function (string $package, string $class, array $parentPackages): bool {
            return $package == 'doctrine/doctrine-bundle';
        });


        self::bootKernel(['config' => static function (TestKernel $kernel) use ($id, $decoratedInspectorId, $arguments): void {
            $kernel->addTestBundle(DoctrineBundle::class);
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal_with_finalizers.yaml');
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/doctrine.yaml');


            $kernel->addTestCompilerPass(new class($id, $decoratedInspectorId, $arguments) implements CompilerPass {
                /**
                 * @param non-empty-string                $id
                 * @param non-empty-string                $decoratedInspectorId
                 * @param non-empty-array<int, Reference> $arguments
                 */
                public function __construct(
                    private readonly string $id,
                    private readonly string $decoratedInspectorId,
                    private readonly array $arguments,
                ) {
                }


                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->hasDefinition($this->id));

                    $def              = $container->getDefinition($this->id);
                    $decoratedService = $def->getDecoratedService();


                    assertNotNull($decoratedService);
                    assertArrayHasKey(0, $decoratedService);
                    assertEquals($this->decoratedInspectorId, $decoratedService[0]);
                    assertEquals($this->arguments, $def->getArguments());
                }
            });
        }]);
    }


    /**
     * @return iterable<array{0: non-empty-string, 1: non-empty-string, 2: non-empty-array<int, Reference>}>
     */
    public static function decorateInspectorDataProvider(): iterable
    {
        yield [
            'temporal_doctrine_ping_connection_default_default.interceptor',
            'temporal.exception_interceptor.default',
            [
                new Reference('temporal.exception_interceptor.default.temporal_doctrine_ping_connection_default_default.interceptor'),
                new Reference('temporal.doctrine_ping_connection_default.finalizer'),
                new Reference('monolog.logger.temporal', Container::IGNORE_ON_INVALID_REFERENCE),
            ],
        ];

        yield [
            'temporal_doctrine_ping_connection_default_foo.interceptor',
            'temporal.exception_interceptor.foo',
            [
                new Reference('temporal.exception_interceptor.foo.temporal_doctrine_ping_connection_default_foo.interceptor'),
                new Reference('temporal.doctrine_ping_connection_default.finalizer'),
                new Reference('monolog.logger.temporal', Container::IGNORE_ON_INVALID_REFERENCE),
            ],
        ];

        yield [
            'temporal_doctrine_ping_connection_customer_foo.interceptor',
            'temporal.exception_interceptor.foo',
            [
                new Reference('temporal.exception_interceptor.foo.temporal_doctrine_ping_connection_customer_foo.interceptor'),
                new Reference('temporal.doctrine_ping_connection_customer.finalizer'),
                new Reference('monolog.logger.temporal', Container::IGNORE_ON_INVALID_REFERENCE),
            ],
        ];

        yield [
            'temporal_doctrine_ping_connection_customer_bar.interceptor',
            'temporal.exception_interceptor.bar',
            [
                new Reference('temporal.exception_interceptor.bar.temporal_doctrine_ping_connection_customer_bar.interceptor'),
                new Reference('temporal.doctrine_ping_connection_customer.finalizer'),
                new Reference('monolog.logger.temporal', Container::IGNORE_ON_INVALID_REFERENCE),
            ],
        ];
    }
}
