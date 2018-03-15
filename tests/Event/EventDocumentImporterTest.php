<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use Broadway\CommandHandling\Testing\TraceableCommandBus;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Address\Locality;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Address\Street;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\CalendarType;
use CultuurNet\UDB3\Event\Commands\CreateEvent;
use CultuurNet\UDB3\Event\Commands\Moderation\Publish;
use CultuurNet\UDB3\Event\Commands\UpdateCalendar;
use CultuurNet\UDB3\Event\Commands\UpdateLocation;
use CultuurNet\UDB3\Event\Commands\UpdateTheme;
use CultuurNet\UDB3\Event\Commands\UpdateTitle;
use CultuurNet\UDB3\Event\Commands\UpdateType;
use CultuurNet\UDB3\Event\Event;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Event\ReadModel\InMemoryDocumentRepository;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\Location\LocationId;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use CultuurNet\UDB3\Model\Import\PreProcessing\LocationPreProcessingDocumentImporter;
use CultuurNet\UDB3\Model\Import\PreProcessing\TermPreProcessingDocumentImporter;
use CultuurNet\UDB3\Model\Place\PlaceIDParser;
use CultuurNet\UDB3\Model\Serializer\Event\EventDenormalizer;
use CultuurNet\UDB3\Theme;
use CultuurNet\UDB3\Timestamp;
use CultuurNet\UDB3\Title;
use PHPUnit\Framework\TestCase;
use ValueObjects\Geography\Country;
use ValueObjects\Geography\CountryCode;
use ValueObjects\StringLiteral\StringLiteral;

class EventDocumentImporterTest extends TestCase
{
    /**
     * @var RepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $repository;

    /**
     * @var EventDenormalizer
     */
    private $denormalizer;

    /**
     * @var TraceableCommandBus
     */
    private $commandBus;

    /**
     * @var EventDocumentImporter
     */
    private $eventDocumentImporter;

    /**
     * @var TermPreProcessingDocumentImporter
     */
    private $termPreProcessingImporter;

    /**
     * @var InMemoryDocumentRepository
     */
    private $placeDocumentRepository;

    /**
     * @var LocationPreProcessingDocumentImporter
     */
    private $locationPreProcessingImporter;

    /**
     * @var DocumentImporterInterface
     */
    private $importer;

    public function setUp()
    {
        $this->repository = $this->createMock(RepositoryInterface::class);
        $this->denormalizer = new EventDenormalizer();
        $this->commandBus = new TraceableCommandBus();

        $this->eventDocumentImporter = new EventDocumentImporter(
            $this->repository,
            $this->denormalizer,
            $this->commandBus
        );

        $this->termPreProcessingImporter = new TermPreProcessingDocumentImporter(
            $this->eventDocumentImporter,
            new EventLegacyBridgeCategoryResolver()
        );

        $this->placeDocumentRepository = new InMemoryDocumentRepository();
        $this->placeDocumentRepository->save(
            $this->getPlaceDocument()->toJsonDocument()
        );

        $this->locationPreProcessingImporter = new LocationPreProcessingDocumentImporter(
            $this->termPreProcessingImporter,
            new PlaceIDParser(),
            $this->placeDocumentRepository
        );

        $this->importer = $this->locationPreProcessingImporter;
    }

    /**
     * @test
     */
    public function it_should_create_an_new_event_and_publish_it_if_no_aggregate_exists_for_the_given_id()
    {
        $document = $this->getEventDocument();
        $id = $document->getId();

        $this->expectEventDoesNotExist($id);

        $this->commandBus->record();

        $this->importer->import($document);

        $expectedCommands = [
            new CreateEvent(
                $id,
                new Language('nl'),
                new Title('Voorbeeld naam'),
                new EventType('0.7.0.0.0', 'Begeleide rondleiding'),
                new Location(
                    'f3277646-1cc8-4af9-b6d5-a47f3c4f2ac0',
                    new StringLiteral('Voorbeeld locatienaam'),
                    new Address(
                        new Street('Henegouwenkaai 41-43'),
                        new PostalCode('1080'),
                        new Locality('Brussel'),
                        new Country(CountryCode::fromNative('BE'))
                    )
                ),
                new Calendar(
                    CalendarType::SINGLE(),
                    \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T12:00:00+01:00'),
                    \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T17:00:00+01:00'),
                    [
                        new Timestamp(
                            \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T12:00:00+01:00'),
                            \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T17:00:00+01:00')
                        ),
                    ],
                    []
                ),
                new Theme('1.17.0.0.0', 'Antiek en brocante'),
                \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T00:00:00+01:00')
            ),
            new Publish(
                $id,
                \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T00:00:00+01:00')
            ),
            new UpdateTitle($id, new Language('fr'), new Title('Nom example')),
            new UpdateTitle($id, new Language('en'), new Title('Example name')),
        ];

        $recordedCommands = $this->commandBus->getRecordedCommands();

        $this->assertEquals($expectedCommands, $recordedCommands);
    }

    /**
     * @test
     */
    public function it_should_update_an_existing_event_if_an_aggregate_exists_for_the_given_id()
    {
        $document = $this->getEventDocument();
        $id = $document->getId();

        $this->expectEventIdExists($id);

        $this->commandBus->record();

        $this->importer->import($document);

        $expectedCommands = [
            new UpdateTitle($id, new Language('nl'), new Title('Voorbeeld naam')),
            new UpdateType($id, new EventType('0.7.0.0.0', 'Begeleide rondleiding')),
            new UpdateLocation($id, new LocationId('f3277646-1cc8-4af9-b6d5-a47f3c4f2ac0')),
            new UpdateCalendar(
                $id,
                new Calendar(
                    CalendarType::SINGLE(),
                    \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T12:00:00+01:00'),
                    \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T17:00:00+01:00'),
                    [
                        new Timestamp(
                            \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T12:00:00+01:00'),
                            \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T17:00:00+01:00')
                        ),
                    ],
                    []
                )
            ),
            new UpdateTheme($id, new Theme('1.17.0.0.0', 'Antiek en brocante')),
            new UpdateTitle($id, new Language('fr'), new Title('Nom example')),
            new UpdateTitle($id, new Language('en'), new Title('Example name')),
        ];

        $recordedCommands = $this->commandBus->getRecordedCommands();

        $this->assertEquals($expectedCommands, $recordedCommands);
    }

    private function getEventId()
    {
        return 'c33b4498-0932-4fbe-816f-c6641f30ba3b';
    }

    /**
     * @return array
     */
    private function getEventData()
    {
        return [
            '@id' => 'https://io.uitdatabank.be/events/c33b4498-0932-4fbe-816f-c6641f30ba3b',
            'mainLanguage' => 'nl',
            'name' => [
                'nl' => 'Voorbeeld naam',
                'fr' => 'Nom example',
                'en' => 'Example name',
            ],
            'calendarType' => 'single',
            'startDate' => '2018-01-01T12:00:00+01:00',
            'endDate' => '2018-01-01T17:00:00+01:00',
            'terms' => [
                [
                    'id' => '0.7.0.0.0',
                ],
                [
                    'id' => '1.17.0.0.0',
                ],
            ],
            'location' => [
                '@id' => 'https://io.uitdatabank.be/places/f3277646-1cc8-4af9-b6d5-a47f3c4f2ac0',
            ],
            'availableFrom' => '2018-01-01T00:00:00+01:00'
        ];
    }

    /**
     * @return DecodedDocument
     */
    private function getEventDocument()
    {
        return new DecodedDocument($this->getEventId(), $this->getEventData());
    }

    /**
     * @return string
     */
    private function getPlaceId()
    {
        return 'f3277646-1cc8-4af9-b6d5-a47f3c4f2ac0';
    }

    /**
     * @return array
     */
    private function getPlaceData()
    {
        return [
            '@id' => 'https://io.uitdatabank.be/places/f3277646-1cc8-4af9-b6d5-a47f3c4f2ac0',
            'mainLanguage' => 'nl',
            'name' => [
                'nl' => 'Voorbeeld locatienaam',
                'fr' => 'Nom example',
                'en' => 'Example name',
            ],
            'calendarType' => 'permanent',
            'terms' => [
                [
                    'id' => '0.14.0.0.0',
                    'label' => 'Monument',
                    'domain' => 'eventtype',
                ],
            ],
            'address' => [
                'nl' => [
                    'streetAddress' => 'Henegouwenkaai 41-43',
                    'postalCode' => '1080',
                    'addressLocality' => 'Brussel',
                    'addressCountry' => 'BE',
                ],
            ],
        ];
    }

    /**
     * @return DecodedDocument
     */
    private function getPlaceDocument()
    {
        return new DecodedDocument($this->getPlaceId(), $this->getPlaceData());
    }

    /**
     * @param string $eventId
     */
    private function expectEventIdExists($eventId)
    {
        $this->repository->expects($this->once())
            ->method('load')
            ->with($eventId)
            ->willReturn($this->createMock(Event::class));
    }

    private function expectEventDoesNotExist($eventId)
    {
        $this->repository->expects($this->once())
            ->method('load')
            ->with($eventId)
            ->willThrowException(new AggregateNotFoundException());
    }
}
