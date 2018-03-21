<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use CultuurNet\UDB3\Address\Address;
use CultuurNet\UDB3\Event\ValueObjects\AudienceType;
use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\Model\Event\Event;
use CultuurNet\UDB3\Model\Import\Offer\Udb3ModelToLegacyOfferAdapter;
use CultuurNet\UDB3\Model\Place\Place;
use ValueObjects\StringLiteral\StringLiteral;

class Udb3ModelToLegacyEventAdapter extends Udb3ModelToLegacyOfferAdapter implements LegacyEvent
{
    /**
     * @var Event
     */
    private $event;

    /**
     * @var Place|null
     */
    private $place;

    /**
     * @param Event $event
     */
    public function __construct(Event $event)
    {
        $place = $event->getPlaceReference()->getEmbeddedPlace();
        if (is_null($place)) {
            throw new \InvalidArgumentException('Embedded place required.');
        }

        parent::__construct($event);
        $this->event = $event;
        $this->place = $place;
    }

    /**
     * @inheritdoc
     */
    public function getLocation()
    {
        return new Location(
            $this->getPlaceId(),
            $this->getPlaceName(),
            $this->getPlaceAddress()
        );
    }

    /**
     * @inheritdoc
     */
    public function getAudienceType()
    {
        $audienceType = $this->event->getAudienceType();

        if ($audienceType) {
            return AudienceType::fromNative($audienceType->toString());
        } else {
            return null;
        }
    }

    /**
     * @return string
     */
    private function getPlaceId()
    {
        return $this->place->getId()->toString();
    }

    /**
     * @return StringLiteral
     */
    private function getPlaceName()
    {
        $title = $this->place->getTitle();

        return new StringLiteral(
            $title->getTranslation(
                $title->getOriginalLanguage()
            )->toString()
        );
    }

    /**
     * @return Address
     */
    private function getPlaceAddress()
    {
        $address = $this->place->getAddress();

        return Address::fromUdb3ModelAddress(
            $address->getTranslation(
                $address->getOriginalLanguage()
            )
        );
    }
}
