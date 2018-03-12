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
use CultuurNet\UDB3\Model\Import\Taxonomy\Category\CategoryResolver;
use CultuurNet\UDB3\Model\Place\ImmutablePlace;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Category;
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
     * @var CategoryResolver
     */
    private $categoryResolver;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    public function __construct(
        DocumentRepositoryInterface $eventDocumentRepository,
        DocumentRepositoryInterface $placeDocumentRepository,
        Serializer $serializer,
        CategoryResolver $categoryResolver,
        CommandBusInterface $commandBus
    ) {
        $this->eventDocumentRepository = $eventDocumentRepository;
        $this->placeDocumentRepository = $placeDocumentRepository;
        $this->serializer = $serializer;
        $this->categoryResolver = $categoryResolver;
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
            $current = null;
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
            function (Category $category) use ($errors) {
                $resolvedCategory = $this->categoryResolver->byId($category->getId());

                if (!$resolvedCategory) {
                    $id = $category->getId()->toString();
                    $errors[] = "Term with id '{$id}' does not exist or is not applicable to event.";
                }

                return $resolvedCategory;
            },
            $import->getTerms()->toArray()
        );
        $categories = array_filter($categories);
        $categories = array_values($categories);

        $types = array_filter(
            $categories,
            function ($category) {
                return $category instanceof EventType;
            }
        );
        if (count($types) !== 1) {
            $errors[] = 'Event must have exactly one term with domain "eventtype".';
            $type = null;
        } else {
            $type = reset($types);
        }

        $themes = array_filter(
            $categories,
            function (Category $category) {
                return $category instanceof Theme;
            }
        );
        if (count($themes) > 1) {
            $errors[] = 'Event can\'t have more than one term with domain "theme".';
            $theme = null;
        } elseif (count($themes) == 1) {
            $theme = reset($themes);
        } else {
            $theme = null;
        }

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
