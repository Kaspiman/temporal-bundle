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
use function PHPUnit\Framework\assertCount;
use function PHPUnit\Framework\assertEquals;
use function PHPUnit\Framework\assertInstanceOf;
use function PHPUnit\Framework\assertTrue;

use PHPUnit\Framework\Attributes\CoversClass;

use PHPUnit\Framework\Attributes\DataProvider;

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as CompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\KernelInterface as Kernel;
use Temporal\Client\WorkflowClientInterface as WorkflowClient;
use Vanta\Integration\Symfony\Temporal\DependencyInjection\Compiler\ClientCompilerPass;
use Vanta\Integration\Symfony\Temporal\TemporalBundle;

/**
 * @phpstan-type ClientOptions array{
 *   withNamespace: non-empty-string,
 *   withIdentity: non-empty-string,
 *   withQueryRejectionCondition: int
 * }
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(ClientCompilerPass::class)]
final class ClientTest extends KernelTestCase
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



    public function testRegisterClientCount(): void
    {
        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');


            $kernel->addTestCompilerPass(new class() implements CompilerPass {
                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->has('temporal.default.client'));
                    assertTrue($container->has('temporal.foo.client'));
                    assertTrue($container->has('temporal.bar.client'));
                }
            });
        }]);
    }


    public function testRegisterClientAliasForArgument(): void
    {
        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');


            $kernel->addTestCompilerPass(new class() implements CompilerPass {
                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->hasAlias('Temporal\Client\WorkflowClientInterface $defaultWorkflowClient'));
                    assertTrue($container->hasAlias('Temporal\Client\WorkflowClientInterface $fooWorkflowClient'));
                    assertTrue($container->hasAlias('Temporal\Client\WorkflowClientInterface $barWorkflowClient'));
                }
            });
        }]);
    }


    /**
     * @param non-empty-string $id
     * @param ClientOptions $options
     */
    #[DataProvider('registerClientOptionsDataProvider')]
    public function testRegisterClientOptions(string $id, array $options): void
    {
        self::bootKernel(['config' => static function (TestKernel $kernel) use ($id, $options): void {
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');


            $kernel->addTestCompilerPass(new class($id, $options) implements CompilerPass {
                /**
                 * @param non-empty-string $id
                 * @param array{
                 *     withNamespace: non-empty-string,
                 *     withIdentity: non-empty-string,
                 *     withQueryRejectionCondition: int
                 * } $options
                 */
                public function __construct(
                    private readonly string $id,
                    private readonly array $options,
                ) {
                }


                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->hasDefinition($this->id));


                    /** @var Definition $def */
                    $def = $container->getDefinition($this->id)
                        ->getArgument('$options')
                    ;

                    assertInstanceOf(Definition::class, $def);

                    foreach ($def->getMethodCalls() as [$method, $arguments, $returnClone]) {
                        assertArrayHasKey($method, $this->options);
                        assertCount(1, $arguments);
                        assertEquals([$this->options[$method]], $arguments);
                        assertTrue($returnClone);
                    }
                }
            });
        }]);
    }


    /**
     * @return iterable<array{0: non-empty-string, 1: ClientOptions}>
     */
    public static function registerClientOptionsDataProvider(): iterable
    {
        yield ['temporal.default.client', ['withNamespace' => 'default', 'withIdentity' => 'default_x', 'withQueryRejectionCondition' => 0]];
        yield ['temporal.foo.client', ['withNamespace' => 'foo', 'withIdentity' => 'foo_x', 'withQueryRejectionCondition' => 1]];
        yield ['temporal.bar.client', ['withNamespace' => 'bar', 'withIdentity' => 'bar_x', 'withQueryRejectionCondition' => 2]];
    }


    public function testRegisterDefaultClient(): void
    {
        self::bootKernel(['config' => static function (TestKernel $kernel): void {
            $kernel->addTestBundle(TemporalBundle::class);
            $kernel->addTestConfig(__DIR__ . '/Framework/Config/temporal.yaml');


            $kernel->addTestCompilerPass(new class() implements CompilerPass {
                public function process(ContainerBuilder $container): void
                {
                    assertTrue($container->hasAlias(WorkflowClient::class));
                    assertEquals('temporal.bar.client', $container->getAlias(WorkflowClient::class)->__toString());
                }
            });
        }]);
    }
}
