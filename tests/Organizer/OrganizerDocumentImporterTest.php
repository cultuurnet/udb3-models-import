<?php

namespace CultuurNet\UDB3\Model\Import\Organizer;

use Broadway\CommandHandling\Testing\TraceableCommandBus;
use Broadway\Repository\AggregateNotFoundException;
use Broadway\Repository\RepositoryInterface;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\Label;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\LabelName;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\Labels;
use CultuurNet\UDB3\Organizer\Commands\ImportLabels;
use CultuurNet\UDB3\Organizer\Commands\UpdateTitle;
use CultuurNet\UDB3\Organizer\Commands\UpdateWebsite;
use CultuurNet\UDB3\Organizer\Organizer;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use CultuurNet\UDB3\Model\Serializer\Organizer\OrganizerDenormalizer;
use CultuurNet\UDB3\Organizer\Commands\CreateOrganizer;
use CultuurNet\UDB3\Title;
use PHPUnit\Framework\TestCase;
use ValueObjects\Web\Url;

class OrganizerDocumentImporterTest extends TestCase
{
    /**
     * @var RepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $repository;

    /**
     * @var OrganizerDenormalizer
     */
    private $denormalizer;

    /**
     * @var TraceableCommandBus
     */
    private $commandBus;

    /**
     * @var OrganizerDocumentImporter
     */
    private $organizerDocumentImporter;

    /**
     * @var DocumentImporterInterface
     */
    private $importer;

    public function setUp()
    {
        $this->repository = $this->createMock(RepositoryInterface::class);
        $this->denormalizer = new OrganizerDenormalizer();
        $this->commandBus = new TraceableCommandBus();

        $this->organizerDocumentImporter = new OrganizerDocumentImporter(
            $this->repository,
            $this->denormalizer,
            $this->commandBus
        );

        $this->importer = $this->organizerDocumentImporter;
    }

    /**
     * @test
     */
    public function it_should_create_a_new_organizer_and_publish_it_if_no_aggregate_exists_for_the_given_id()
    {
        $document = $this->getOrganizerDocument();
        $id = $document->getId();

        $this->expectOrganizerDoesNotExist($id);

        $this->commandBus->record();

        $this->importer->import($document);

        $expectedCommands = [
            new CreateOrganizer(
                $id,
                new Language('nl'),
                Url::fromNative('https://www.publiq.be'),
                new Title('Voorbeeld naam')
            ),
            new UpdateTitle($id, new Title('Nom example'), new Language('fr')),
            new UpdateTitle($id, new Title('Example name'), new Language('en')),
        ];

        $recordedCommands = $this->commandBus->getRecordedCommands();

        $this->assertEquals($expectedCommands, $recordedCommands);
    }

    /**
     * @test
     */
    public function it_should_update_an_existing_organizer_if_an_aggregate_exists_for_the_given_id()
    {
        $document = $this->getOrganizerDocument();
        $id = $document->getId();

        $this->expectOrganizerExists($id);

        $this->commandBus->record();

        $this->importer->import($document);

        $expectedCommands = [
            new UpdateTitle($id, new Title('Voorbeeld naam'), new Language('nl')),
            new UpdateWebsite($id, Url::fromNative('https://www.publiq.be')),
            new UpdateTitle($id, new Title('Nom example'), new Language('fr')),
            new UpdateTitle($id, new Title('Example name'), new Language('en')),
        ];

        $recordedCommands = $this->commandBus->getRecordedCommands();

        $this->assertEquals($expectedCommands, $recordedCommands);
    }

    /**
     * @test
     */
    public function it_should_update_an_existing_organizer_with_labels()
    {
        $document = $this->getOrganizerDocumentWithLabels();
        $id = $document->getId();

        $this->expectOrganizerExists($id);

        $this->commandBus->record();

        $this->importer->import($document);

        $expectedCommands = $this->getExpectedCommands() + [
            new ImportLabels(
                $this->getOrganizerId(),
                new Labels(
                    new Label(new LabelName('foo'), true),
                    new Label(new LabelName('bar'), true),
                    new Label(new LabelName('lorem'), false),
                    new Label(new LabelName('ipsum'), false)
                )
            )
        ];

        $recordedCommands = $this->commandBus->getRecordedCommands();

        $this->assertEquals($expectedCommands, $recordedCommands);
    }

    /**
     * @return string
     */
    private function getOrganizerId()
    {
        return 'f3277646-1cc8-4af9-b6d5-a47f3c4f2ac0';
    }

    /**
     * @return array
     */
    private function getOrganizerData()
    {
        return [
            '@id' => 'https://io.uitdatabank.be/organizers/f3277646-1cc8-4af9-b6d5-a47f3c4f2ac0',
            'mainLanguage' => 'nl',
            'name' => [
                'nl' => 'Voorbeeld naam',
                'fr' => 'Nom example',
                'en' => 'Example name',
            ],
            'url' => 'https://www.publiq.be',
        ];
    }

    /**
     * @return array
     */
    private function getOrganizerDataWithLabels()
    {
        return $this->getOrganizerData() + [
            [
                'labels' => [
                    'foo',
                    'bar',
                ]
            ],
            [
                'hiddenLabels' => [
                    'lorem',
                    'ipsum',
                ]
            ]
        ];
    }

    /**
     * @return DecodedDocument
     */
    private function getOrganizerDocument()
    {
        return new DecodedDocument($this->getOrganizerId(), $this->getOrganizerData());
    }

    /**
     * @return DecodedDocument
     */
    private function getOrganizerDocumentWithLabels()
    {
        return new DecodedDocument($this->getOrganizerId(), $this->getOrganizerDataWithLabels());
    }

    /**
     * @return array
     */
    private function getExpectedCommands()
    {
        $id = $this->getOrganizerId();

        return [
            new UpdateTitle($id, new Title('Voorbeeld naam'), new Language('nl')),
            new UpdateWebsite($id, Url::fromNative('https://www.publiq.be')),
            new UpdateTitle($id, new Title('Nom example'), new Language('fr')),
            new UpdateTitle($id, new Title('Example name'), new Language('en')),
        ];
    }

    /**
     * @param string $organizerId
     */
    private function expectOrganizerExists($organizerId)
    {
        $this->repository->expects($this->once())
            ->method('load')
            ->with($organizerId)
            ->willReturn($this->createMock(Organizer::class));
    }

    private function expectOrganizerDoesNotExist($organizerId)
    {
        $this->repository->expects($this->once())
            ->method('load')
            ->with($organizerId)
            ->willThrowException(new AggregateNotFoundException());
    }
}
