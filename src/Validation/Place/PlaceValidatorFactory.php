<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Place;

use CultuurNet\UDB3\Model\Import\Place\PlaceLegacyBridgeCategoryResolver;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Category\CategoriesExistValidator;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Category\EventTypeCountValidator;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Category\ThemeCountValidator;
use CultuurNet\UDB3\Model\Validation\DocumentValidatorFactory;
use CultuurNet\UDB3\Model\Validation\Place\PlaceValidator;
use Respect\Validation\Rules\AllOf;
use Respect\Validation\Rules\Key;

class PlaceValidatorFactory implements DocumentValidatorFactory
{
    /**
     * @inheritdoc
     */
    public function forDocumentId($id)
    {
        $extraRules = [
            new Key(
                'terms',
                new AllOf(
                    new CategoriesExistValidator(new PlaceLegacyBridgeCategoryResolver(), 'place'),
                    new EventTypeCountValidator(),
                    new ThemeCountValidator()
                ),
                false
            ),
        ];

        return new PlaceValidator($extraRules);}
}
