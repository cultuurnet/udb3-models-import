<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\Category;
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
use CultuurNet\UDB3\Model\Place\ImmutablePlace;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Theme;
use CultuurNet\UDB3\Title;
use Respect\Validation\Exceptions\GroupedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Symfony\Component\Serializer\Serializer;
use ValueObjects\StringLiteral\StringLiteral;

/**
 * @todo Move validation errors to a separate EventImportValidator so they can be combined
 * with the the errors from EventValidator.
 */
class EventJsonImporter implements JsonImporterInterface
{
    /**
     * @var DocumentRepositoryInterface
     */
    private $eventDocumentRepository;

    /**
     * @var DocumentRepositoryInterface
     */
    private $placeDocumentRepository;

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
        DocumentRepositoryInterface $placeDocumentRepository,
        Serializer $serializer,
        CommandBusInterface $commandBus
    ) {
        $this->eventDocumentRepository = $eventDocumentRepository;
        $this->placeDocumentRepository = $placeDocumentRepository;
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

        /* @var Event $import */
        $import = $this->serializer->deserialize($data, Event::class, 'json');

        try {
            $currentDocument = $this->eventDocumentRepository->get($id);

            $current = $this->serializer->deserialize(
                $currentDocument->getRawBody(),
                Event::class,
                'json'
            );
        } catch (DocumentGoneException $e) {
            throw new ValidationException('The Event with the given id has been deleted and cannot be re-created.');
        }

        $errors = [];

        $mainLanguage = Language::fromUdb3ModelLanguage(
            $import->getMainLanguage()
        );

        $title = Title::fromUdb3ModelTitle(
            $import->getTitle()->getTranslation(
                $import->getMainLanguage()
            )
        );

        $categories = array_map(
            function (array $category) {
                return new Category(
                    $category['id'],
                    $category['label'],
                    $category['domain']
                );
            },
            $import->getTerms()->toArray()
        );

        $types = array_filter(
            $categories,
            function ($category) {
                return $category instanceof EventType;
            }
        );
        $type = reset($types);

        $themes = array_filter(
            $categories,
            function (Category $category) {
                return $category instanceof Theme;
            }
        );
        $theme = count($themes) > 0 ? reset($themes) : null;

        $placeId = $import->getPlaceReference()->getPlaceId();
        $placeJsonDocument = null;
        $location = null;

        try {
            $placeJsonDocument = $this->placeDocumentRepository->get($placeId->toString());
            if ($placeId->sameAs(ImmutablePlace::getDummyLocationId())) {
                $errors = 'Can not import events with a dummy location.';
            } elseif (!$placeJsonDocument) {
                $errors = 'The given location id does not exist.';
            }
        } catch (DocumentGoneException $e) {
            $errors = 'The given location id is deleted and can not be coupled to the event.';
        }

        // @todo Refactor CreateEvent, Event::create(), and EventCreated to use LocationID instead
        // of Location. Afterwards this whole block can be simplified.
        if ($placeJsonDocument) {
            $placeJson = $placeJsonDocument->getBody();

            $placeMainLanguage = isset($placeJson->mainLanguage) ? $placeJson->mainLanguage : 'nl';
            $placeName = $placeJson->name;
            if (is_array($placeName)) {
                $placeName = $placeName[$placeMainLanguage];
            }

            $placeAddress = $placeJson->address;
            if (is_array($placeAddress)) {
                $placeAddress = $placeAddress[$placeMainLanguage];
            }

            $location = new Location(
                $import->getPlaceReference()->getPlaceId()->toString(),
                new StringLiteral($placeName),
                Address::deserialize($placeAddress)
            );
        }

        $calendar = Calendar::fromUdb3ModelCalendar($import->getCalendar());

        $publishDate = $import->getAvailableFrom();

        if (!empty($errors)) {
            $groupedException = new GroupedValidationException('event');
            foreach ($errors as $error) {
                $exception = new ValidationException($error);
                $groupedException->addRelated($exception);
            }
            throw new $groupedException;
        }

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
