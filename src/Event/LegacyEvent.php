<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use CultuurNet\UDB3\Event\ValueObjects\AudienceType;
use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\Model\Import\Offer\LegacyOffer;

interface LegacyEvent extends LegacyOffer
{
    /**
     * @return Location
     */
    public function getLocation();

    /**
     * @return AudienceType
     */
    public function getAudienceType();
}
