<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Event;

use CultuurNet\UDB3\Event\ReadModel\DocumentRepositoryInterface;
use CultuurNet\UDB3\Model\Validation\Event\EventValidator;

class EventValidatorFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_creates_event_validator_for_document_id()
    {
        /**
         * @var DocumentRepositoryInterface $placeRepository
         */
        $placeRepository = $this->createMock(DocumentRepositoryInterface::class);

        $placeValidatorFactory = new EventValidatorFactory($placeRepository);

        $placeValidator = $placeValidatorFactory->forDocumentId('document_id');

        $this->assertInstanceOf(EventValidator::class, $placeValidator);
    }
}
