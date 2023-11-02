<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\accounting\Data\Stores\Bases;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SimpleSAML\Module\accounting\Data\Stores\Bases\AbstractStore;
use SimpleSAML\Module\accounting\ModuleConfiguration;
use SimpleSAML\Module\accounting\Services\Serializers\PhpSerializer;

/**
 * @covers \SimpleSAML\Module\accounting\Data\Stores\Bases\AbstractStore
 * @uses \SimpleSAML\Module\accounting\Factories\SerializerFactory
 * @uses \SimpleSAML\Module\accounting\Services\Serializers\PhpSerializer
 */
class AbstractStoreTest extends TestCase
{
    protected AbstractStore $abstractStore;
    /**
     * @var Stub
     */
    protected $moduleConfigurationStub;
    /**
     * @var Stub
     */
    protected $loggerStub;
    protected MockObject $serializerMock;

    protected function setUp(): void
    {
        $this->moduleConfigurationStub = $this->createStub(ModuleConfiguration::class);
        $this->moduleConfigurationStub->method('getSerializerClass')->willReturn(PhpSerializer::class);
        $this->loggerStub = $this->createStub(LoggerInterface::class);

        $this->abstractStore = new class (
            $this->moduleConfigurationStub,
            $this->loggerStub
        ) extends AbstractStore {
            public static function build(
                ModuleConfiguration $moduleConfiguration,
                LoggerInterface $logger,
                string $connectionKey = null
            ): AbstractStore {
                return new self($moduleConfiguration, $logger, $connectionKey);
            }
            public function needsSetup(): bool
            {
                return false;
            }
            public function runSetup(): void
            {
            }
        };
    }

    public function testCanBuildInstance(): void
    {
        $this->assertInstanceOf(AbstractStore::class, $this->abstractStore);
        $this->assertInstanceOf(
            AbstractStore::class,
            $this->abstractStore::build($this->moduleConfigurationStub, $this->loggerStub)
        );
    }
}
