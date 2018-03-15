<?php

namespace CultuurNet\UDB3\Model\Import\Place;

use Broadway\CommandHandling\Testing\TraceableCommandBus;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Address\Locality;
use CultuurNet\UDB3\Address\PostalCode;
use CultuurNet\UDB3\Address\Street;
use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\CalendarType;
use CultuurNet\UDB3\Place\Commands\Moderation\Publish;
use CultuurNet\UDB3\Place\Commands\UpdateCalendar;
use CultuurNet\UDB3\Place\Commands\UpdateTheme;
use CultuurNet\UDB3\Place\Commands\UpdateTitle;
use CultuurNet\UDB3\Place\Commands\UpdateType;
use CultuurNet\UDB3\Place\Place;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use CultuurNet\UDB3\Model\Import\PreProcessing\TermPreProcessingDocumentImporter;
use CultuurNet\UDB3\Model\Serializer\Place\PlaceDenormalizer;
use CultuurNet\UDB3\Place\Commands\CreatePlace;
use CultuurNet\UDB3\Place\Commands\UpdateAddress;
use CultuurNet\UDB3\Theme;
use CultuurNet\UDB3\Timestamp;
use CultuurNet\UDB3\Title;
use PHPUnit\Framework\TestCase;
use ValueObjects\Geography\Country;
use ValueObjects\Geography\CountryCode;

class PlaceDocumentImporterTest extends TestCase
{
    /**
     * @var RepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $repository;

    /**
     * @var PlaceDenormalizer
     */
    private $denormalizer;

    /**
     * @var TraceableCommandBus
     */
    private $commandBus;

    /**
     * @var PlaceDocumentImporter
     */
    private $placeDocumentImporter;

    /**
     * @var TermPreProcessingDocumentImporter
     */
    private $termPreProcessingImporter;

    /**
     * @var DocumentImporterInterface
     */
    private $importer;

    public function setUp()
    {
        $this->repository = $this->createMock(RepositoryInterface::class);
        $this->denormalizer = new PlaceDenormalizer();
        $this->commandBus = new TraceableCommandBus();

        $this->placeDocumentImporter = new PlaceDocumentImporter(
            $this->repository,
            $this->denormalizer,
            $this->commandBus
        );

        $this->termPreProcessingImporter = new TermPreProcessingDocumentImporter(
            $this->placeDocumentImporter,
            new PlaceLegacyBridgeCategoryResolver()
        );

        $this->importer = $this->termPreProcessingImporter;
    }

    /**
     * @test
     */
    public function it_should_create_a_new_place_and_publish_it_if_no_aggregate_exists_for_the_given_id()
    {
        $document = $this->getPlaceDocument();
        $id = $document->getId();

        $this->expectPlaceDoesNotExist($id);

        $this->commandBus->record();

        $this->importer->import($document);

        $expectedCommands = [
            new CreatePlace(
                $id,
                new Language('nl'),
                new Title('Voorbeeld naam'),
                new EventType('0.14.0.0.0', 'Monument'),
                new Address(
                    new Street('Henegouwenkaai 41-43'),
                    new PostalCode('1080'),
                    new Locality('Brussel'),
                    new Country(CountryCode::fromNative('BE'))
                ),
                new Calendar(CalendarType::PERMANENT()),
                null,
                \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T00:00:00+01:00')
            ),
            new Publish(
                $id,
                \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T00:00:00+01:00')
            ),
            new UpdateTitle($id, new Language('fr'), new Title('Nom example')),
            new UpdateTitle($id, new Language('en'), new Title('Example name')),
            new UpdateAddress(
                $id,
                new Address(
                    new Street('Quai du Hainaut 41-43'),
                    new PostalCode('1080'),
                    new Locality('Bruxelles'),
                    new Country(CountryCode::fromNative('BE'))
                ),
                new Language('fr')
            ),
            new UpdateAddress(
                $id,
                new Address(
                    new Street('Henegouwenkaai 41-43'),
                    new PostalCode('1080'),
                    new Locality('Brussels'),
                    new Country(CountryCode::fromNative('BE'))
                ),
                new Language('en')
            ),
        ];

        $recordedCommands = $this->commandBus->getRecordedCommands();

        $this->assertEquals($expectedCommands, $recordedCommands);
    }

    /**
     * @test
     */
    public function it_should_update_an_existing_place_if_an_aggregate_exists_for_the_given_id()
    {
        $document = $this->getPlaceDocument();
        $id = $document->getId();

        $this->expectPlaceExists($id);

        $this->commandBus->record();

        $this->importer->import($document);

        $expectedCommands = [
            new UpdateTitle($id, new Language('nl'), new Title('Voorbeeld naam')),
            new UpdateType($id, new EventType('0.14.0.0.0', 'Monument')),
            new UpdateAddress(
                $id,
                new Address(
                    new Street('Henegouwenkaai 41-43'),
                    new PostalCode('1080'),
                    new Locality('Brussel'),
                    new Country(CountryCode::fromNative('BE'))
                ),
                new Language('nl')
            ),
            new UpdateCalendar($id, new Calendar(CalendarType::PERMANENT())),
            new UpdateTitle($id, new Language('fr'), new Title('Nom example')),
            new UpdateTitle($id, new Language('en'), new Title('Example name')),
            new UpdateAddress(
                $id,
                new Address(
                    new Street('Quai du Hainaut 41-43'),
                    new PostalCode('1080'),
                    new Locality('Bruxelles'),
                    new Country(CountryCode::fromNative('BE'))
                ),
                new Language('fr')
            ),
            new UpdateAddress(
                $id,
                new Address(
                    new Street('Henegouwenkaai 41-43'),
                    new PostalCode('1080'),
                    new Locality('Brussels'),
                    new Country(CountryCode::fromNative('BE'))
                ),
                new Language('en')
            ),
        ];

        $recordedCommands = $this->commandBus->getRecordedCommands();

        $this->assertEquals($expectedCommands, $recordedCommands);
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
                'nl' => 'Voorbeeld naam',
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
                'fr' => [
                    'streetAddress' => 'Quai du Hainaut 41-43',
                    'postalCode' => '1080',
                    'addressLocality' => 'Bruxelles',
                    'addressCountry' => 'BE',
                ],
                'en' => [
                    'streetAddress' => 'Henegouwenkaai 41-43',
                    'postalCode' => '1080',
                    'addressLocality' => 'Brussels',
                    'addressCountry' => 'BE',
                ],
            ],
            'availableFrom' => '2018-01-01T00:00:00+01:00',
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
     * @param string $placeId
     */
    private function expectPlaceExists($placeId)
    {
        $this->repository->expects($this->once())
            ->method('load')
            ->with($placeId)
            ->willReturn($this->createMock(Place::class));
    }

    private function expectPlaceDoesNotExist($placeId)
    {
        $this->repository->expects($this->once())
            ->method('load')
            ->with($placeId)
            ->willThrowException(new AggregateNotFoundException());
    }
}
