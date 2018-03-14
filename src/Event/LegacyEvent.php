<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\Model\Import\Offer\LegacyOffer;

interface LegacyEvent extends LegacyOffer
{
    /**
     * @return Location
     */
    public function getLocation();
}
