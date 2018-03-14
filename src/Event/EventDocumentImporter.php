<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\Event\Commands\CreateEvent;
use CultuurNet\UDB3\Event\Commands\Moderation\Publish;
use CultuurNet\UDB3\Event\Commands\UpdateCalendar;
use CultuurNet\UDB3\Event\Commands\UpdateLocation;
use CultuurNet\UDB3\Event\Commands\UpdateTheme;
use CultuurNet\UDB3\Event\Commands\UpdateTitle;
use CultuurNet\UDB3\Event\Commands\UpdateType;
use CultuurNet\UDB3\Event\ReadModel\DocumentGoneException;
use CultuurNet\UDB3\Event\ReadModel\DocumentRepositoryInterface;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Location\LocationId;
use CultuurNet\UDB3\Model\Event\Event;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use Respect\Validation\Exceptions\ValidationException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class EventDocumentImporter implements DocumentImporterInterface
{
    /**
     * @var DocumentRepositoryInterface
     */
    private $eventDocumentRepository;

    /**
     * @var DenormalizerInterface
     */
    private $eventDenormalizer;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    public function __construct(
        DocumentRepositoryInterface $eventDocumentRepository,
        DenormalizerInterface $eventDenormalizer,
        CommandBusInterface $commandBus
    ) {
        $this->eventDocumentRepository = $eventDocumentRepository;
        $this->eventDenormalizer = $eventDenormalizer;
        $this->commandBus = $commandBus;
    }

    /**
     * @inheritdoc
     */
    public function import(DecodedDocument $decodedDocument)
    {
        $id = $decodedDocument->getId();

        // Try to get the current document to check that it hasn't been deleted in the past,
        // before we validate and denormalize the import data.
        try {
            $current = null;
            $currentDocument = $this->eventDocumentRepository->get($id);

            if ($currentDocument) {
                $currentDocument = DecodedDocument::fromJsonDocument($currentDocument);
                $currentData = $currentDocument->getBody();
                $current = $this->eventDenormalizer->denormalize($currentData, Event::class);
            }
        } catch (DocumentGoneException $e) {
            throw new ValidationException('The Event with the given id has been deleted and cannot be re-created.');
        } catch (\Exception $e) {
            throw new \LogicException('Could not deserialize internal event read model!', 0, $e);
        }

        /* @var Event $import */
        $importData = $decodedDocument->getBody();
        $import = $this->eventDenormalizer->denormalize($importData, Event::class);

        $adapter = new Udb3ModelToLegacyEventAdapter($import);

        $mainLanguage = $adapter->getMainLanguage();
        $title = $adapter->getTitle();
        $type = $adapter->getType();
        $theme = $adapter->getTheme();
        $location = $adapter->getLocation();
        $calendar = $adapter->getCalendar();
        $publishDate = $adapter->getAvailableFrom(new \DateTimeImmutable());

        $commands = [];
        if (!$current) {
            $commands[] = new CreateEvent(
                $id,
                $mainLanguage,
                $title,
                $type,
                $location,
                $calendar,
                $theme,
                $publishDate
            );

            // New events created via the import API should always be set to
            // ready_for_validation.
            // The publish date in EventCreated does not seem to trigger a
            // wfStatus "ready_for_validation" on the json-ld so we manually
            // publish the event after creating it.
            // Existing events should always keep their original status, so
            // only do this publish command for new events.
            $commands[] = new Publish($id, $publishDate);
        } else {
            $commands[] = new UpdateTitle(
                $id,
                $mainLanguage,
                $title
            );

            $commands[] = new UpdateType($id, $type);
            $commands[] = new UpdateLocation($id, new LocationId($location->getCdbid()));
            $commands[] = new UpdateCalendar($id, $calendar);
            $commands[] = new UpdateTheme($id, $theme);
        }

        foreach ($adapter->getTitleTranslations() as $language => $title) {
            $language = new Language($language);
            $commands[] = new UpdateTitle($id, $language, $title);
        }

        foreach ($commands as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
