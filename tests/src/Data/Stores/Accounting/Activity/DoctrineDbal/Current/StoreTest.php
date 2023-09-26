<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current;

use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Log\LoggerInterface;
use SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store\TableConstants;
use SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\EntityTableConstants;
use SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\HashDecoratedState;
use SimpleSAML\Module\accounting\Data\Stores\Connections\DoctrineDbal\Connection;
use SimpleSAML\Module\accounting\Data\Stores\Connections\DoctrineDbal\Factory;
use SimpleSAML\Module\accounting\Data\Stores\Connections\DoctrineDbal\Migrator;
use SimpleSAML\Module\accounting\Entities\Authentication\Event;
use SimpleSAML\Module\accounting\Exceptions\StoreException;
use SimpleSAML\Module\accounting\Exceptions\StoreException\MigrationException;
use SimpleSAML\Module\accounting\ModuleConfiguration;
use SimpleSAML\Module\accounting\Services\HelpersManager;
use SimpleSAML\Test\Module\accounting\Constants\ConnectionParameters;
use SimpleSAML\Test\Module\accounting\Constants\RawRowResult;
use SimpleSAML\Test\Module\accounting\Constants\StateArrays;
use SimpleSAML\Module\accounting\Entities\Authentication\Event\State;
// phpcs:ignore
use SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Current\Store\TableConstants as BaseTableConstants;
// phpcs:ignore
use SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Versioned\Store\TableConstants as VersionedTableConstants;

/**
 * @covers \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Bases\DoctrineDbal\AbstractStore
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Connections\DoctrineDbal\Connection
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Connections\DoctrineDbal\Migrator
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Connections\DoctrineDbal\Factory
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store\Repository
 * @uses \SimpleSAML\Module\accounting\Entities\Authentication\Event
 * @uses \SimpleSAML\Module\accounting\Entities\Bases\AbstractState
 * @uses \SimpleSAML\Module\accounting\Entities\Authentication\Event\State\Saml2
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Connections\Bases\AbstractMigrator
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Connections\DoctrineDbal\Bases\AbstractMigration
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store\Migrations\Version20220801000200CreateSpTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store\Migrations\Version20220801000400CreateUserTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store\Migrations\Version20220801000500CreateUserVersionTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Versioned\Store\Migrations\Version20220801000400CreateUserTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Versioned\Store\Migrations\Version20220801000500CreateUserVersionTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\Current\Store\Migrations\Version20220801000700CreateAuthenticationEventTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Current\Store
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\HashDecoratedState
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Current\Store\Repository
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Current\Store\Migrations\CreateSpTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Current\Store\Migrations\CreateUserTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Versioned\Store\Migrations\CreateUserTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Current\Store\Migrations\CreateUserVersionTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Bases\DoctrineDbal\Versioned\Store\Migrations\CreateUserVersionTable
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Bases\AbstractStore
 * @uses \SimpleSAML\Module\accounting\Helpers\Filesystem
 * @uses \SimpleSAML\Module\accounting\Helpers\Hash
 * @uses \SimpleSAML\Module\accounting\Helpers\Arr
 * @uses \SimpleSAML\Module\accounting\Helpers\Network
 * @uses \SimpleSAML\Module\accounting\Entities\ConnectedService\Bag
 * @uses \SimpleSAML\Module\accounting\Entities\Bases\AbstractProvider
 * @uses \SimpleSAML\Module\accounting\Entities\ConnectedService
 * @uses \SimpleSAML\Module\accounting\Entities\User
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Bases\DoctrineDbal\AbstractRawEntity
 * @uses \SimpleSAML\Module\accounting\Entities\Activity\Bag
 * @uses \SimpleSAML\Module\accounting\Entities\Activity
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Accounting\Activity\DoctrineDbal\RawActivity
 * @uses \SimpleSAML\Module\accounting\Services\HelpersManager
 * @uses \SimpleSAML\Module\accounting\Data\Stores\Bases\AbstractStore
 * @uses \SimpleSAML\Module\accounting\Entities\Providers\Service\Saml2
 * @uses \SimpleSAML\Module\accounting\Helpers\ProviderResolver
 * @uses \SimpleSAML\Module\accounting\Entities\Authentication\Protocol\Saml2
 */
class StoreTest extends TestCase
{
    protected Stub $moduleConfigurationStub;
    protected Migrator $migrator;
    protected Stub $factoryStub;
    protected Connection $connection;
    protected State\Saml2 $state;
    protected Event $authenticationEvent;
    protected HashDecoratedState $hashDecoratedState;
    /**
     * @var MockObject
     */
    protected $repositoryMock;
    /**
     * @var Stub
     */
    protected $resultStub;
    /**
     * @var MockObject
     */
    protected $loggerMock;
    /**
     * @var MockObject
     */
    protected $helpersManagerMock;

    /**
     * @throws StoreException
     */
    protected function setUp(): void
    {
        $connectionParams = ConnectionParameters::DBAL_SQLITE_MEMORY;
        $this->moduleConfigurationStub = $this->createStub(ModuleConfiguration::class);
        $this->moduleConfigurationStub->method('getConnectionParameters')
            ->willReturn($connectionParams);
        $this->moduleConfigurationStub->method('getUserIdAttributeName')
            ->willReturn('hrEduPersonPersistentID');

        $this->connection = new Connection($connectionParams);

        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->migrator = new Migrator($this->connection, $this->loggerMock);

        $this->factoryStub = $this->createStub(Factory::class);
        $this->factoryStub->method('buildConnection')->willReturn($this->connection);
        $this->factoryStub->method('buildMigrator')->willReturn($this->migrator);

        $this->state = new State\Saml2(StateArrays::SAML2_FULL);
        $this->authenticationEvent = new Event($this->state);

        $this->hashDecoratedState = new HashDecoratedState($this->state);
        $this->repositoryMock = $this->createMock(
            Store\Repository::class
        );

        $this->resultStub = $this->createStub(Result::class);
        $this->helpersManagerMock = $this->createMock(HelpersManager::class);
    }

    /**
     * @throws StoreException
     */
    public function testCanConstructInstance(): void
    {
        $this->assertInstanceOf(
            Store::class,
            new Store(
                $this->moduleConfigurationStub,
                $this->loggerMock,
                null,
                ModuleConfiguration\ConnectionType::MASTER,
                $this->factoryStub
            )
        );
    }

    /**
     * @throws StoreException
     */
    public function testCanBuildInstance(): void
    {
        $this->assertInstanceOf(
            Store::class,
            Store::build($this->moduleConfigurationStub, $this->loggerMock)
        );
    }

    /**
     * @throws StoreException
     * @throws Exception
     * @throws MigrationException
     */
    public function testCanPersistAuthenticationEvent(): void
    {
        $store = new Store(
            $this->moduleConfigurationStub,
            $this->loggerMock,
            null,
            ModuleConfiguration\ConnectionType::MASTER,
            $this->factoryStub
        );
        $store->runSetup();

        $spCountQueryBuilder = $this->connection->dbal()->createQueryBuilder();
        $userCountQueryBuilder = $this->connection->dbal()->createQueryBuilder();
        $userVersionCountQueryBuilder = $this->connection->dbal()->createQueryBuilder();
        $authenticationEventCountQueryBuilder = $this->connection->dbal()->createQueryBuilder();

        $spCountQueryBuilder->select('COUNT(id) as spCount')->from(
        //'vds_sp'
            $this->connection->preparePrefixedTableName(
                BaseTableConstants::TABLE_PREFIX . BaseTableConstants::TABLE_NAME_SP
            )
        );
        $userCountQueryBuilder->select('COUNT(id) as userCount')->from(
        //'vds_user'
            $this->connection->preparePrefixedTableName(
                VersionedTableConstants::TABLE_PREFIX . VersionedTableConstants::TABLE_NAME_USER
            )
        );
        $userVersionCountQueryBuilder->select('COUNT(id) as userVersionCount')->from(
        //'vds_user_version'
            $this->connection->preparePrefixedTableName(
                VersionedTableConstants::TABLE_PREFIX . VersionedTableConstants::TABLE_NAME_USER_VERSION
            )
        );
        $authenticationEventCountQueryBuilder->select('COUNT(id) as authenticationEventCount')
            ->from(
            //'vds_authentication_event'
                $this->connection->preparePrefixedTableName(
                    BaseTableConstants::TABLE_PREFIX . TableConstants::TABLE_NAME_AUTHENTICATION_EVENT
                )
            );

        $this->assertSame(0, (int)$spCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(0, (int)$userCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(0, (int)$userVersionCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(0, (int)$authenticationEventCountQueryBuilder->executeQuery()->fetchOne());

        $store->persist($this->authenticationEvent);

        $this->assertSame(1, (int)$spCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(1, (int)$userCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(1, (int)$userVersionCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(1, (int)$authenticationEventCountQueryBuilder->executeQuery()->fetchOne());

        $store->persist($this->authenticationEvent);

        $this->assertSame(1, (int)$spCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(1, (int)$userCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(1, (int)$userVersionCountQueryBuilder->executeQuery()->fetchOne());
        $this->assertSame(2, (int)$authenticationEventCountQueryBuilder->executeQuery()->fetchOne());
    }

    /**
     * @throws StoreException
     */
    public function testGetActivityReturnsEmptyBagIfNoResults(): void
    {
        $this->repositoryMock->method('getActivity')->willReturn([]);

        $store = new Store(
            $this->moduleConfigurationStub,
            $this->loggerMock,
            null,
            ModuleConfiguration\ConnectionType::MASTER,
            $this->factoryStub,
            $this->helpersManagerMock,
            $this->repositoryMock
        );

        $activityBag = $store->getActivity('test', 10, 0);

        $this->assertEmpty($activityBag->getAll());
    }

    /**
     * @throws StoreException
     */
    public function testCanGetActivityBag(): void
    {
        $this->repositoryMock->method('getActivity')
            ->willReturn([RawRowResult::ACTIVITY]);

        $store = new Store(
            $this->moduleConfigurationStub,
            $this->loggerMock,
            null,
            ModuleConfiguration\ConnectionType::MASTER,
            $this->factoryStub,
            $this->helpersManagerMock,
            $this->repositoryMock
        );

        $activityBag = $store->getActivity('test', 10, 0);

        $this->assertNotEmpty($activityBag->getAll());
    }

    /**
     * @throws StoreException
     */
    public function testGetActivityThrowsForInvalidResult(): void
    {
        $rawResult = RawRowResult::ACTIVITY;
        unset($rawResult[EntityTableConstants::ENTITY_ACTIVITY_COLUMN_NAME_HAPPENED_AT]);

        $this->repositoryMock->method('getActivity')
            ->willReturn([$rawResult]);

        $store = new Store(
            $this->moduleConfigurationStub,
            $this->loggerMock,
            null,
            ModuleConfiguration\ConnectionType::MASTER,
            $this->factoryStub,
            $this->helpersManagerMock,
            $this->repositoryMock
        );

        $this->expectException(StoreException::class);
        $store->getActivity('test', 10, 0);
    }

    /**
     * @throws StoreException
     */
    public function testCanDeleteDataOlderThan(): void
    {
        $dateTime = new DateTimeImmutable();

        $this->repositoryMock->expects($this->once())
            ->method('deleteAuthenticationEventsOlderThan')
            ->with($dateTime);

        $store = new Store(
            $this->moduleConfigurationStub,
            $this->loggerMock,
            null,
            ModuleConfiguration\ConnectionType::MASTER,
            $this->factoryStub,
            $this->helpersManagerMock,
            $this->repositoryMock
        );

        $store->deleteDataOlderThan($dateTime);
    }
}
