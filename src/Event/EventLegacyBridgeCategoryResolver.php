<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use CultuurNet\UDB3\Event\EventThemeResolver;
use CultuurNet\UDB3\Event\EventTypeResolver;
use CultuurNet\UDB3\Model\Import\Taxonomy\Category\LegacyBridgeCategoryResolver;
use CultuurNet\UDB3\Symfony\Event\EventFacilityResolver;

class EventLegacyBridgeCategoryResolver extends LegacyBridgeCategoryResolver
{
    public function __construct()
    {
        parent::__construct(new EventTypeResolver(), new EventThemeResolver(), new EventFacilityResolver());
    }
}
