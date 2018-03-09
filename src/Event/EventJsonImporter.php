<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use Broadway\CommandHandling\CommandBusInterface;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\Event\Commands\CreateEvent;
use CultuurNet\UDB3\Event\Commands\UpdateAudience;
use CultuurNet\UDB3\Event\Commands\UpdateTitle;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Event\ReadModel\DocumentGoneException;
use CultuurNet\UDB3\Event\ReadModel\DocumentRepositoryInterface;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Model\Event\Event;
use CultuurNet\UDB3\Model\Import\JsonImporterInterface;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Category;
use CultuurNet\UDB3\Offer\ThemeResolverInterface;
use CultuurNet\UDB3\Offer\TypeResolverInterface;
use CultuurNet\UDB3\ReadModel\JsonDocument;
use CultuurNet\UDB3\Theme;
use CultuurNet\UDB3\Title;
use Respect\Validation\Exceptions\GroupedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Symfony\Component\Serializer\Serializer;
use ValueObjects\StringLiteral\StringLiteral;

class EventJsonImporter implements JsonImporterInterface
{
    /**
     * @var DocumentRepositoryInterface
     */
    private $documentRepository;

    /**
     * @var Serializer
     */
    private $serializer;

    /**
     * @var TypeResolverInterface
     */
    private $typeResolver;

    /**
     * @var ThemeResolverInterface
     */
    private $themeResolver;

    /**
     * @var CommandBusInterface
     */
    private $commandBus;

    public function __construct(
        DocumentRepositoryInterface $documentRepository,
        Serializer $serializer,
        CommandBusInterface $commandBus,
        TypeResolverInterface $typeResolver,
        ThemeResolverInterface $themeResolver
    ) {
        $this->documentRepository = $documentRepository;
        $this->serializer = $serializer;
        $this->commandBus = $commandBus;
        $this->typeResolver = $typeResolver;
        $this->themeResolver = $themeResolver;
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
            $currentDocument = $this->documentRepository->get($id);

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
            function (Category $category) {
                try {
                    return $this->typeResolver->byId(new StringLiteral($category->getId()->toString()));
                } catch (\Exception $e) {
                }

                try {
                    return $this->themeResolver->byId(new StringLiteral($category->getId()->toString()));
                } catch (\Exception $e) {
                }

                // @todo Add facility resolver etc. Ideally we'd have one single CategoryResolver.
                return null;
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
        } else {

        }

        foreach ($commands as $command) {
            $this->commandBus->dispatch($command);
        }
    }
}
