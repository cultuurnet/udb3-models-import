<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Event;

use CultuurNet\UDB3\Event\ReadModel\DocumentRepositoryInterface;
use CultuurNet\UDB3\Model\Import\Event\EventLegacyBridgeCategoryResolver;
use CultuurNet\UDB3\Model\Import\Validation\Place\PlaceReferenceExistsValidator;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Category\CategoriesExistValidator;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Category\EventTypeCountValidator;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Category\ThemeCountValidator;
use CultuurNet\UDB3\Model\Place\PlaceIDParser;
use CultuurNet\UDB3\Model\Validation\DocumentValidatorFactory;
use CultuurNet\UDB3\Model\Validation\Event\EventValidator;
use Respect\Validation\Rules\AllOf;
use Respect\Validation\Rules\Key;

class EventValidatorFactory implements DocumentValidatorFactory
{
    /**
     * @var DocumentRepositoryInterface
     */
    private $placeRepository;

    /**
     * @param DocumentRepositoryInterface $placeRepository
     */
    public function __construct(DocumentRepositoryInterface $placeRepository)
    {
        $this->placeRepository = $placeRepository;
    }

    /**
     * @inheritdoc
     */
    public function forDocumentId($id)
    {
        $extraRules = [
            new PlaceReferenceExistsValidator(
                new PlaceIDParser(),
                $this->placeRepository
            ),
            new Key(
                'terms',
                new AllOf(
                    new CategoriesExistValidator(new EventLegacyBridgeCategoryResolver(), 'event'),
                    new EventTypeCountValidator(),
                    new ThemeCountValidator()
                ),
                false
            ),
        ];

        return new EventValidator($extraRules);
    }
}
