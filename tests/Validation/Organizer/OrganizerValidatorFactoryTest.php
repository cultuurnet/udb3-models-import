<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Organizer;

use CultuurNet\UDB3\Label\ReadModels\JSON\Repository\ReadRepositoryInterface as LabelsRepository;
use CultuurNet\UDB3\Label\ReadModels\Relations\Repository\ReadRepositoryInterface as LabelRelationsRepository;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Label\LabelPermissionRule;
use CultuurNet\UDB3\Model\Organizer\OrganizerIDParser;
use CultuurNet\UDB3\Model\Validation\Organizer\OrganizerValidator;
use CultuurNet\UDB3\Organizer\DBALWebsiteLookupService;
use CultuurNet\UDB3\Security\UserIdentificationInterface;
use Doctrine\DBAL\Connection;
use ValueObjects\StringLiteral\StringLiteral;

class OrganizerValidatorFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    private $documentId;

    /**
     * @var Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    private $connection;

    /**
     * @var UserIdentificationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $userIdentification;

    /**
     * @var LabelsRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    private $labelsRepository;

    /**
     * @var LabelRelationsRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    private $labelRelationsRepository;

    /**
     * @ver LabelPermissionRule
     */
    private $labelPermissionRule;

    protected function setUp()
    {
        $this->documentId = 'f32227be-a621-4cbd-8803-19762d7f9a23';

        $this->connection = $this->createMock(Connection::class);

        $this->userIdentification = $this->createMock(UserIdentificationInterface::class);

        $this->labelsRepository = $this->createMock(LabelsRepository::class);

        $this->labelRelationsRepository = $this->createMock(LabelRelationsRepository::class);

        $this->labelPermissionRule = new LabelPermissionRule(
            new StringLiteral($this->documentId),
            $this->userIdentification,
            $this->labelsRepository,
            $this->labelRelationsRepository
        );
    }

    /**
     * @test
     */
    public function it_creates_organizer_validator_for_document_id()
    {
        $organizerValidatorFactory = new OrganizerValidatorFactory(
            $this->connection,
            $this->userIdentification,
            $this->labelsRepository,
            $this->labelRelationsRepository
        );

        $organizerValidator = $organizerValidatorFactory->forDocumentId($this->documentId);

        // Rule arrays are associative so can't be compared.
        $rules = $organizerValidator->getRules();
        $this->assertCount(10, $rules);
        $this->assertInstanceOf(LabelPermissionRule::class, end($rules));
        $this->assertInstanceOf(OrganizerHasUniqueUrlValidator::class, prev($rules));
    }
}
