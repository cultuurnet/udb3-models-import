<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\Event\Commands\CreateEvent;
use CultuurNet\UDB3\Event\Commands\Moderation\Publish;
use CultuurNet\UDB3\Event\Commands\UpdateCalendar;
use CultuurNet\UDB3\Event\Commands\UpdateLocation;
use CultuurNet\UDB3\Event\Commands\UpdateTheme;
use CultuurNet\UDB3\Event\Commands\UpdateTitle;
use CultuurNet\UDB3\Event\Commands\UpdateType;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Event\ReadModel\DocumentGoneException;
use CultuurNet\UDB3\Event\ReadModel\DocumentRepositoryInterface;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\Location\LocationId;
use CultuurNet\UDB3\Model\Event\Event;
use CultuurNet\UDB3\Model\Import\JsonImporterInterface;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Category;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryDomain;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Theme;
use CultuurNet\UDB3\Title;
use Respect\Validation\Exceptions\ValidationException;
use Symfony\Component\Serializer\Serializer;
use ValueObjects\StringLiteral\StringLiteral;

class EventJsonImporter implements JsonImporterInterface
{
    /**
     * @var DocumentRepositoryInterface
     */
    private $eventDocumentRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    public function __construct(
        DocumentRepositoryInterface $eventDocumentRepository,
        Serializer $serializer,
        CommandBusInterface $commandBus
    ) {
        $this->eventDocumentRepository = $eventDocumentRepository;
        $this->serializer = $serializer;
        $this->commandBus = $commandBus;
    }

    /**
     * @param JsonDocument $jsonDocument
     */
    public function import(JsonDocument $jsonDocument)
    {
        $id = $jsonDocument->getId();
        $data = $jsonDocument->getRawBody();

        try {
            $currentDocument = $this->eventDocumentRepository->get($id);

            $current = $this->serializer->deserialize(
                $currentDocument->getRawBody(),
                Event::class,
                'json'
            );
        } catch (DocumentGoneException $e) {
            throw new ValidationException('The Event with the given id has been deleted and cannot be re-created.');
        } catch (\Exception $e) {
            throw new \LogicException('Could not deserialize internal event read model!', 0, $e);
        }

        /* @var Event $import */
        $import = $this->serializer->deserialize($data, Event::class, 'json');

        $mainLanguage = Language::fromUdb3ModelLanguage(
            $import->getMainLanguage()
        );

        $title = Title::fromUdb3ModelTitle(
            $import->getTitle()->getTranslation(
                $import->getMainLanguage()
            )
        );

        $type = $import->getTerms()
            ->filter(
                function (Category $term) {
                    return $term->getDomain()->sameAs(new CategoryDomain('eventtype'));
                }
            )
            ->getFirst();
        $type = EventType::fromUdb3Model($type);

        $theme = $import->getTerms()
            ->filter(
                function (Category $term) {
                    return $term->getDomain()->sameAs(new CategoryDomain('theme'));
                }
            )
            ->getFirst();
        $theme = $theme ? Theme::fromUdb3Model($theme) : null;

        $place = $import->getPlaceReference()->getEmbeddedPlace();
        $placeId = $place->getId();
        $placeName = $place->getTitle()->getTranslation($place->getTitle()->getOriginalLanguage());
        $placeAddress = $place->getAddress()->getTranslation($place->getAddress()->getOriginalLanguage());

        $location = new Location(
            $import->getPlaceReference()->getPlaceId()->toString(),
            new StringLiteral($placeName->toString()),
            Address::fromUdb3Model($placeAddress)
        );

        $calendar = Calendar::fromUdb3ModelCalendar($import->getCalendar());

        $publishDate = $import->getAvailableFrom();

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
        } else {
            $commands[] = new UpdateTitle(
                $id,
                $mainLanguage,
                $title
            );

            $commands[] = new UpdateType($id, $type);
            $commands[] = new UpdateLocation($id, new LocationId($placeId->toString()));
            $commands[] = new UpdateCalendar($id, $calendar);
            $commands[] = new UpdateTheme($id, $theme);

            if (!is_null($publishDate)) {
                $commands[] = new Publish($id, $publishDate);
            }
        }

        /* @var \CultuurNet\UDB3\Model\ValueObject\Translation\Language $language */
        foreach ($import->getTitle()->getLanguages() as $language) {
            if ($language->sameAs($import->getMainLanguage())) {
                continue;
            }

            $commands[] = new UpdateTitle(
                $id,
                Language::fromUdb3ModelLanguage($language),
                Title::fromUdb3ModelTitle($import->getTitle()->getTranslation($language))
            );
        }

        foreach ($commands as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
