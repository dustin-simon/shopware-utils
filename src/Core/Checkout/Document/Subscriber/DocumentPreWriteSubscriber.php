<?php

namespace Dustin\ShopwareUtils\Core\Checkout\Document\Subscriber;

use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class DocumentPreWriteSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => [
                ['fixDocumentOrderVersionId']
            ]
        ];
    }

    // This fixes a Shopware bug where documents get assigned the live version id
    public function fixDocumentOrderVersionId(PreWriteValidationEvent $event): void
    {
        $context = $event->getContext();

        if($context->getVersionId() !== Defaults::LIVE_VERSION) {
            return;
        }

        foreach($event->getCommands() as $command) {
            if(!$command instanceof UpdateCommand || $command->getEntityName() !== DocumentDefinition::ENTITY_NAME) {
                continue;
            }

            $payload = $command->getPayload();
            $orderVersionId = $payload['order_version_id'] ?? null;

            if($orderVersionId === null) {
                continue;
            }

            $orderVersionId = Uuid::fromBytesToHex($orderVersionId);

            if($orderVersionId !== Defaults::LIVE_VERSION) {
                continue;
            }

            unset($payload['order_version_id']);

            $reflectionClass = new \ReflectionClass(WriteCommand::class);
            $reflectionClass->getProperty('payload')->setValue($command, $payload);
        }
    }
}