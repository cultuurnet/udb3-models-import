<?php

namespace CultuurNet\UDB3\Model\Import\Organizer;

use Broadway\CommandHandling\CommandBusInterface;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use CultuurNet\UDB3\Model\Organizer\Organizer;
use CultuurNet\UDB3\Organizer\Commands\CreateOrganizer;
use CultuurNet\UDB3\Organizer\Commands\ImportLabels;
use CultuurNet\UDB3\Organizer\Commands\UpdateAddress;
use CultuurNet\UDB3\Organizer\Commands\UpdateContactPoint;
use CultuurNet\UDB3\Organizer\Commands\UpdateTitle;
use CultuurNet\UDB3\Organizer\Commands\UpdateWebsite;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class OrganizerDocumentImporter implements DocumentImporterInterface
{
    /**
     * @var RepositoryInterface
     */
    private $aggregateRepository;

    /**
     * @var DenormalizerInterface
     */
    private $organizerDenormalizer;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    public function __construct(
        RepositoryInterface $aggregateRepository,
        DenormalizerInterface $organizerDenormalizer,
        CommandBusInterface $commandBus
    ) {
        $this->aggregateRepository = $aggregateRepository;
        $this->organizerDenormalizer = $organizerDenormalizer;
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

        /* @var Organizer $import */
        $importData = $decodedDocument->getBody();
        $import = $this->organizerDenormalizer->denormalize($importData, Organizer::class);

        $adapter = new Udb3ModelToLegacyOrganizerAdapter($import);

        $mainLanguage = $adapter->getMainLanguage();
        $title = $adapter->getTitle();
        $url = $adapter->getWebsite();

        $commands = [];
        if (!$exists) {
            $commands[] = new CreateOrganizer(
                $id,
                $mainLanguage,
                $url,
                $title
            );
        } else {
            $commands[] = new UpdateTitle(
                $id,
                $title,
                $mainLanguage
            );

            $commands[] = new UpdateWebsite($id, $url);
        }

        $contactPoint = $adapter->getContactPoint();
        $commands[] = new UpdateContactPoint($id, $contactPoint);

        $address = $adapter->getAddress();
        if ($address) {
            $commands[] = new UpdateAddress($id, $address);
        }

        foreach ($adapter->getTitleTranslations() as $language => $title) {
            $language = new Language($language);
            $commands[] = new UpdateTitle($id, $title, $language);
        }

        if ($import->getLabels()->count() > 0) {
            $commands[] = new ImportLabels($id, $import->getLabels());
        }

        foreach ($commands as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
