<?php

namespace CultuurNet\UDB3\Model\Import\Place;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Place\Commands\CreatePlace;
use CultuurNet\UDB3\Place\Commands\Moderation\Publish;
use CultuurNet\UDB3\Place\Commands\UpdateAddress;
use CultuurNet\UDB3\Place\Commands\UpdateCalendar;
use CultuurNet\UDB3\Place\Commands\UpdateTheme;
use CultuurNet\UDB3\Place\Commands\UpdateTitle;
use CultuurNet\UDB3\Place\Commands\UpdateType;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Model\Place\Place;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PlaceDocumentImporter implements DocumentImporterInterface
{
    /**
     * @var RepositoryInterface
     */
    private $aggregateRepository;

    /**
     * @var DenormalizerInterface
     */
    private $placeDenormalizer;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    public function __construct(
        RepositoryInterface $aggregateRepository,
        DenormalizerInterface $placeDenormalizer,
        CommandBusInterface $commandBus
    ) {
        $this->aggregateRepository = $aggregateRepository;
        $this->placeDenormalizer = $placeDenormalizer;
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

        /* @var Place $import */
        $importData = $decodedDocument->getBody();
        $import = $this->placeDenormalizer->denormalize($importData, Place::class);

        $adapter = new Udb3ModelToLegacyPlaceAdapter($import);

        $mainLanguage = $adapter->getMainLanguage();
        $title = $adapter->getTitle();
        $type = $adapter->getType();
        $theme = $adapter->getTheme();
        $address = $adapter->getAddress();
        $calendar = $adapter->getCalendar();
        $publishDate = $adapter->getAvailableFrom(new \DateTimeImmutable());

        $commands = [];
        if (!$exists) {
            $commands[] = new CreatePlace(
                $id,
                $mainLanguage,
                $title,
                $type,
                $address,
                $calendar,
                $theme,
                $publishDate
            );

            // New places created via the import API should always be set to
            // ready_for_validation.
            // The publish date in PlaceCreated does not seem to trigger a
            // wfStatus "ready_for_validation" on the json-ld so we manually
            // publish the place after creating it.
            // Existing places should always keep their original status, so
            // only do this publish command for new places.
            $commands[] = new Publish($id, $publishDate);
        } else {
            $commands[] = new UpdateTitle(
                $id,
                $mainLanguage,
                $title
            );

            $commands[] = new UpdateType($id, $type);
            $commands[] = new UpdateAddress($id, $address, $mainLanguage);
            $commands[] = new UpdateCalendar($id, $calendar);

            if ($theme) {
                $commands[] = new UpdateTheme($id, $theme);
            }
        }

        foreach ($adapter->getTitleTranslations() as $language => $title) {
            $language = new Language($language);
            $commands[] = new UpdateTitle($id, $language, $title);
        }

        foreach ($adapter->getAddressTranslations() as $language => $address) {
            $language = new Language($language);
            $commands[] = new UpdateAddress($id, $address, $language);
        }

        foreach ($commands as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}