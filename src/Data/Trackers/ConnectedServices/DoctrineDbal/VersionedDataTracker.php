<?php

declare(strict_types=1);

namespace SimpleSAML\Module\accounting\Data\Trackers\ConnectedServices\DoctrineDbal;

use DateInterval;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use SimpleSAML\Module\accounting\Data\Providers\ConnectedServices\DoctrineDbal\VersionedDataProvider;
use SimpleSAML\Module\accounting\Data\Trackers\Interfaces\DataTrackerInterface;
use SimpleSAML\Module\accounting\Entities\Authentication\Event;
use SimpleSAML\Module\accounting\Exceptions\StoreException;
use SimpleSAML\Module\accounting\ModuleConfiguration;

class VersionedDataTracker extends VersionedDataProvider implements DataTrackerInterface
{
    /**
     * @throws StoreException
     */
    public static function build(
        ModuleConfiguration $moduleConfiguration,
        LoggerInterface $logger,
        string $connectionType = ModuleConfiguration\ConnectionType::MASTER
    ): self {
        return new self($moduleConfiguration, $logger, $connectionType);
    }

    /**
     * @throws StoreException
     * @throws Exception
     */
    public function process(Event $authenticationEvent): void
    {
        $this->store->persist($authenticationEvent);
    }

    public function enforceDataRetentionPolicy(DateInterval $retentionPolicy): void
    {
        $dateTime = (new DateTimeImmutable())->sub($retentionPolicy);

        $this->store->deleteDataOlderThan($dateTime);
    }
}
