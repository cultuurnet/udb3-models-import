<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Place;

use CultuurNet\UDB3\Model\Validation\Place\PlaceValidator;

class PlaceValidatorFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function it_creates_place_validator_for_document_id()
    {
        $placeValidatorFactory = new PlaceValidatorFactory();

        $placeValidator = $placeValidatorFactory->forDocumentId('document_id');

        $this->assertInstanceOf(PlaceValidator::class, $placeValidator);
    }
}
