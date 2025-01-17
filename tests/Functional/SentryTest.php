<?php
/**
 * Temporal Bundle
 *
 * @author Vlad Shashkov <v.shashkov@pos-credit.ru>
 * @copyright Copyright (c) 2023, The Vanta
 */

declare(strict_types=1);

namespace Vanta\Integration\Symfony\Temporal\Test\Functional;

use Nyholm\BundleTest\TestKernel;

use function PHPUnit\Framework\assertArrayHasKey;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertFalse;
use function PHPUnit\Framework\assertIsArray;
use function PHPUnit\Framework\assertNotNull;
use function PHPUnit\Framework\assertTrue;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

use Sentry\SentryBundle\SentryBundle;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\KernelInterface as Kernel;
use Vanta\Integration\Symfony\Temporal\DependencyInjection\Compiler\SentryCompilerPass;
use Vanta\Integration\Symfony\Temporal\DependencyInjection\TemporalExtension;
use Vanta\Integration\Symfony\Temporal\InstalledVersions;
use Vanta\Integration\Symfony\Temporal\TemporalBundle;

#[CoversClass(TemporalExtension::class)]
#[CoversClass(SentryCompilerPass::class)]
final class SentryTest extends KernelTestCase
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


    /**
     * @param non-empty-string $id
     */
    #[DataProvider('notFoundHubDataProvider')]
    public function testNotFoundHub(string $id): void
    {
        InstalledVersions::setHandler(static function (string $package, string $class, array $parentPackages): bool {
            return $package == 'sentry/sentry-symfony';
        });

        self::bootKernel(['config' => static function (TestKernel $kernel) use ($id): void {
            $kernel->addTestBundle(MonologBundle::class);
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/monolog.yaml');


            $kernel->addTestCompilerPass(new class($id) implements CompilerPass {
                /**
                 * @param non-empty-string $id
                 */
                public function __construct(
                    private readonly string $id
                ) {
                }


                public function process(ContainerBuilder $container): void
                {
                    assertFalse($container->hasDefinition($this->id));
                }
            });
        }]);
    }


    /**
     * @return iterable<array<int, non-empty-string>>
     */
    public static function notFoundHubDataProvider(): iterable
    {
        yield ['temporal.sentry_default.interceptor'];
        yield ['temporal.sentry_foo.interceptor'];
        yield ['temporal.sentry_bar.interceptor'];
    }


    /**
     * @param non-empty-string $id
     * @param non-empty-string $decoratedId
     */
    #[DataProvider('decorateTemporalInspectorDataProvider')]
    public function testDecorateTemporalInspector(string $id, string $decoratedId): void
    {
        InstalledVersions::setHandler(static function (string $package, string $class, array $parentPackages): bool {
            return $package == 'sentry/sentry-symfony';
        });

        self::bootKernel(['config' => static function (TestKernel $kernel) use ($id, $decoratedId): void {
            $kernel->addTestBundle(SentryBundle::class);
            $kernel->addTestBundle(MonologBundle::class);
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/sentry.yaml');


            $kernel->addTestCompilerPass(new class($id, $decoratedId) implements CompilerPass {
                /**
                 * @param non-empty-string $id
                 * @param non-empty-string $decoratedId
                 */
                public function __construct(
                    private readonly string $id,
                    private readonly string $decoratedId,
                ) {
                }


                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->hasDefinition($this->id));

                    $decoratedService = $container->getDefinition($this->id)
                        ->getDecoratedService()
                    ;

                    assertNotNull($decoratedService);
                    assertIsArray($decoratedService);
                    assertArrayHasKey(0, $decoratedService);
                    assertEquals($this->decoratedId, $decoratedService[0]);
                }
            });
        }]);
    }


    /**
     * @return iterable<array<int, non-empty-string>>
     */
    public static function decorateTemporalInspectorDataProvider(): iterable
    {
        yield ['temporal.sentry_default.interceptor', 'temporal.exception_interceptor.default'];
        yield ['temporal.sentry_foo.interceptor', 'temporal.exception_interceptor.foo'];
        yield ['temporal.sentry_bar.interceptor', 'temporal.exception_interceptor.bar'];
    }
}
