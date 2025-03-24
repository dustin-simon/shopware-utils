<?php

namespace Dustin\ShopwareUtils\Core\System\SystemConfig;

use Doctrine\DBAL\Connection;
use Dustin\ShopwareUtils\Core\Framework\Struct\Encapsulation;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigFactory
{
    /** @var array<string, string[]>|null */
    private ?array $salesChannelIds = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly SystemConfigService $configService
    ) {}

    public function createConfigPerSalesChannel(string $name, array $salesChannelTypes = []): Encapsulation
    {
        $result = [];

        foreach($this->getSalesChannelIds($salesChannelTypes) as $salesChannelId) {
            $result[$salesChannelId] = $this->createConfig($name, $salesChannelId);
        }

        return new Encapsulation($result);
    }

    public function createConfig(string $name, ?string $salesChannelId = null): Encapsulation
    {
        $config = $this->configService->get($name, $salesChannelId);

        return new Encapsulation((array) $config);
    }

    private function getSalesChannelIds(array $salesChannelTypes = []): array
    {
        $this->loadSalesChannelIds();
        $ids = [];

        foreach((array) $this->salesChannelIds as $typeId => $salesChannelIds) {
            if(empty($salesChannelTypes) || \in_array($typeId, $salesChannelTypes, true)) {
                \array_push($ids, ...$salesChannelIds);
            }
        }

        return $ids;
    }

    private function loadSalesChannelIds(): void
    {
        if($this->salesChannelIds !== null) {
            return;
        }

        $salesChannels = $this->connection->fetchAllAssociative('SELECT `id`, `type_id` FROM `sales_channel`');
        $this->salesChannelIds = [];

        foreach($salesChannels as $salesChannel) {
            $id = Uuid::fromBytesToHex($salesChannel['id']);
            $typeId = Uuid::fromBytesToHex($salesChannel['type_id']);

            $this->salesChannelIds[$typeId][] = $id;
        }
    }
}