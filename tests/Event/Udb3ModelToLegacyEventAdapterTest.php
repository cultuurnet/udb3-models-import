<?php

namespace CultuurNet\UDB3\Model\Import\Event;

use CultuurNet\UDB3\Location\Location;
use CultuurNet\UDB3\Model\Event\ImmutableEvent;
use CultuurNet\UDB3\Model\Place\ImmutablePlace;
use CultuurNet\UDB3\Model\Place\PlaceReference;
use CultuurNet\UDB3\Model\ValueObject\Calendar\OpeningHours\OpeningHours;
use CultuurNet\UDB3\Model\ValueObject\Calendar\PermanentCalendar;
use CultuurNet\UDB3\Model\ValueObject\Geography\Address;
use CultuurNet\UDB3\Model\ValueObject\Geography\CountryCode;
use CultuurNet\UDB3\Model\ValueObject\Geography\Locality;
use CultuurNet\UDB3\Model\ValueObject\Geography\PostalCode;
use CultuurNet\UDB3\Model\ValueObject\Geography\Street;
use CultuurNet\UDB3\Model\ValueObject\Geography\TranslatedAddress;
use CultuurNet\UDB3\Model\ValueObject\Identity\UUID;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Categories;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Category;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryDomain;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryID;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryLabel;
use CultuurNet\UDB3\Model\ValueObject\Text\Title;
use CultuurNet\UDB3\Model\ValueObject\Text\TranslatedTitle;
use CultuurNet\UDB3\Model\ValueObject\Translation\Language;
use PHPUnit\Framework\TestCase;
use ValueObjects\Geography\Country;
use ValueObjects\StringLiteral\StringLiteral;

class Udb3ModelToLegacyEventAdapterTest extends TestCase
{
    /**
     * @var ImmutableEvent
     */
    private $event;

    /**
     * @var Udb3ModelToLegacyEventAdapter
     */
    private $adapter;

    public function setUp()
    {
        $this->event = new ImmutableEvent(
            new UUID('91060c19-a860-4a47-8591-8a779bfa520a'),
            new Language('nl'),
            (new TranslatedTitle(new Language('nl'), new Title('Voorbeeld titel')))
                ->withTranslation(new Language('fr'), new Title('Titre example'))
                ->withTranslation(new Language('en'), new Title('Example title')),
            new PermanentCalendar(new OpeningHours()),
            PlaceReference::createWithEmbeddedPlace(
                new ImmutablePlace(
                    new UUID('6ba87a6b-efea-4467-9e87-458d145384d9'),
                    new Language('nl'),
                    new TranslatedTitle(new Language('nl'), new Title('Voorbeeld titel')),
                    new PermanentCalendar(new OpeningHours()),
                    new TranslatedAddress(
                        new Language('nl'),
                        new Address(
                            new Street('Henegouwenkaai 41-43'),
                            new PostalCode('1080'),
                            new Locality('Brussel'),
                            new CountryCode('BE')
                        )
                    ),
                    new Categories(
                        new Category(
                            new CategoryID('0.14.0.0.0'),
                            new CategoryLabel('Monument'),
                            new CategoryDomain('eventtype')
                        )
                    )
                )
            ),
            new Categories(
                new Category(
                    new CategoryID('0.6.0.0.0'),
                    new CategoryLabel('Beurs'),
                    new CategoryDomain('eventtype')
                ),
                new Category(
                    new CategoryID('0.52.0.0.0'),
                    new CategoryLabel('Circus'),
                    new CategoryDomain('theme')
                )
            )
        );

        $this->event = $this->event->withAvailableFrom(
            \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T10:00:00+01:00')
        );

        $this->adapter = new Udb3ModelToLegacyEventAdapter($this->event);
    }

    /**
     * @test
     */
    public function it_should_return_the_embedded_location()
    {
        $expected = new Location(
            '6ba87a6b-efea-4467-9e87-458d145384d9',
            new StringLiteral('Voorbeeld titel'),
            new \CultuurNet\UDB3\Address\Address(
                new \CultuurNet\UDB3\Address\Street('Henegouwenkaai 41-43'),
                new \CultuurNet\UDB3\Address\PostalCode('1080'),
                new \CultuurNet\UDB3\Address\Locality('Brussel'),
                new Country(\ValueObjects\Geography\CountryCode::fromNative('BE'))
            )
        );
        $actual = $this->adapter->getLocation();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_throw_an_exception_if_an_event_without_embedded_place_is_injected()
    {
        $placeReference = PlaceReference::createWithPlaceId(new UUID('6ba87a6b-efea-4467-9e87-458d145384d9'));
        $event = $this->event->withPlaceReference($placeReference);

        $this->expectException(\InvalidArgumentException::class);

        new Udb3ModelToLegacyEventAdapter($event);
    }
}