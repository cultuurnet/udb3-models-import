<?php

namespace CultuurNet\UDB3\Model\Import\Place;

use CultuurNet\UDB3\Place\PlaceThemeResolver;
use CultuurNet\UDB3\Place\PlaceTypeResolver;
use CultuurNet\UDB3\Model\Import\Taxonomy\Category\LegacyBridgeCategoryResolver;
use CultuurNet\UDB3\Symfony\Place\PlaceFacilityResolver;

class PlaceLegacyBridgeCategoryResolver extends LegacyBridgeCategoryResolver
{
    public function __construct()
    {
        parent::__construct(new PlaceTypeResolver(), new PlaceThemeResolver(), new PlaceFacilityResolver());
    }
}
