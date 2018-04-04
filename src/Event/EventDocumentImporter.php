<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Event\Commands\CreateEvent;
use CultuurNet\UDB3\Event\Commands\ImportLabels;
use CultuurNet\UDB3\Event\Commands\DeleteCurrentOrganizer;
use CultuurNet\UDB3\Event\Commands\DeleteTypicalAgeRange;
use CultuurNet\UDB3\Event\Commands\ImportImages;
use CultuurNet\UDB3\Event\Commands\Moderation\Publish;
use CultuurNet\UDB3\Event\Commands\UpdateAudience;
use CultuurNet\UDB3\Event\Commands\UpdateBookingInfo;
use CultuurNet\UDB3\Event\Commands\UpdateCalendar;
use CultuurNet\UDB3\Event\Commands\UpdateContactPoint;
use CultuurNet\UDB3\Event\Commands\UpdateDescription;
use CultuurNet\UDB3\Event\Commands\UpdateLocation;
use CultuurNet\UDB3\Event\Commands\UpdateOrganizer;
use CultuurNet\UDB3\Event\Commands\UpdatePriceInfo;
use CultuurNet\UDB3\Event\Commands\UpdateTheme;
use CultuurNet\UDB3\Event\Commands\UpdateTitle;
use CultuurNet\UDB3\Event\Commands\UpdateType;
use CultuurNet\UDB3\Event\Commands\UpdateTypicalAgeRange;
use CultuurNet\UDB3\Event\ValueObjects\Audience;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Location\LocationId;
use CultuurNet\UDB3\Model\Event\Event;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use CultuurNet\UDB3\Model\Import\MediaObject\ImageCollectionFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class EventDocumentImporter implements DocumentImporterInterface
{
    /**
     * @var RepositoryInterface
     */
    private $aggregateRepository;

    /**
     * @var DenormalizerInterface
     */
    private $eventDenormalizer;

    /**
     * @var ImageCollectionFactory
     */
    private $imageCollectionFactory;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    public function __construct(
        RepositoryInterface $aggregateRepository,
        DenormalizerInterface $eventDenormalizer,
        ImageCollectionFactory $imageCollectionFactory,
        CommandBusInterface $commandBus
    ) {
        $this->aggregateRepository = $aggregateRepository;
        $this->eventDenormalizer = $eventDenormalizer;
        $this->imageCollectionFactory = $imageCollectionFactory;
        $this->commandBus = $commandBus;
    }

    /**
     * @inheritdoc
     */
    public function import(DecodedDocument $decodedDocument)
    {
        $id = $decodedDocument->getId();

        try {
            $exists = !is_null($this->aggregateRepository->load($id));
        } catch (AggregateNotFoundException $e) {
            $exists = false;
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
        if (!$exists) {
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

            if ($theme) {
                $commands[] = new UpdateTheme($id, $theme);
            }
        }

        $audienceType = $adapter->getAudienceType();
        $commands[] = new UpdateAudience($id, new Audience($audienceType));

        $bookingInfo = $adapter->getBookingInfo();
        $commands[] = new UpdateBookingInfo($id, $bookingInfo);

        $contactPoint = $adapter->getContactPoint();
        $commands[] = new UpdateContactPoint($id, $contactPoint);

        $description = $adapter->getDescription();
        if ($description) {
            $commands[] = new UpdateDescription($id, $mainLanguage, $description);
        }

        $organizerId = $adapter->getOrganizerId();
        if ($organizerId) {
            $commands[] = new UpdateOrganizer($id, $organizerId);
        } else {
            $commands[] = new DeleteCurrentOrganizer($id);
        }

        $ageRange = $adapter->getAgeRange();
        if ($ageRange) {
            $commands[] = new UpdateTypicalAgeRange($id, $ageRange);
        } else {
            $commands[] = new DeleteTypicalAgeRange($id);
        }

        $priceInfo = $adapter->getPriceInfo();
        if ($priceInfo) {
            $commands[] = new UpdatePriceInfo($id, $priceInfo);
        }

        foreach ($adapter->getTitleTranslations() as $language => $title) {
            $language = new Language($language);
            $commands[] = new UpdateTitle($id, $language, $title);
        }

        foreach ($adapter->getDescriptionTranslations() as $language => $description) {
            $language = new Language($language);
            $commands[] = new UpdateDescription($id, $language, $description);
        }

        $commands[] = new ImportLabels($id, $import->getLabels());

        $images = $this->imageCollectionFactory->fromMediaObjectReferences($import->getMediaObjectReferences());
        $commands[] = new ImportImages($id, $images);

        foreach ($commands as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
