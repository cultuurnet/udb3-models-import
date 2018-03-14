<?php

namespace CultuurNet\UDB3\Model\Import\Offer;

use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\CalendarType;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Model\Event\ImmutableEvent;
use CultuurNet\UDB3\Model\Offer\ImmutableOffer;
use CultuurNet\UDB3\Model\Place\PlaceReference;
use CultuurNet\UDB3\Model\ValueObject\Calendar\OpeningHours\OpeningHours;
use CultuurNet\UDB3\Model\ValueObject\Calendar\PermanentCalendar;
use CultuurNet\UDB3\Model\ValueObject\Identity\UUID;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Categories;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Category;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryDomain;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryID;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryLabel;
use CultuurNet\UDB3\Model\ValueObject\Text\Title;
use CultuurNet\UDB3\Model\ValueObject\Text\TranslatedTitle;
use CultuurNet\UDB3\Model\ValueObject\Translation\Language;
use CultuurNet\UDB3\Theme;
use PHPUnit\Framework\TestCase;

class Udb3ModelToLegacyOfferAdapterTest extends TestCase
{
    /**
     * @var ImmutableOffer
     */
    private $offer;

    /**
     * @var Udb3ModelToLegacyOfferAdapter
     */
    private $adapter;

    public function setUp()
    {
        $this->offer = new ImmutableEvent(
            new UUID('91060c19-a860-4a47-8591-8a779bfa520a'),
            new Language('nl'),
            (new TranslatedTitle(new Language('nl'), new Title('Voorbeeld titel')))
                ->withTranslation(new Language('fr'), new Title('Titre example'))
                ->withTranslation(new Language('en'), new Title('Example title')),
            new PermanentCalendar(new OpeningHours()),
            PlaceReference::createWithPlaceId(
                new UUID('6ba87a6b-efea-4467-9e87-458d145384d9')
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

        $this->offer = $this->offer->withAvailableFrom(
            \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T10:00:00+01:00')
        );

        $this->adapter = new Udb3ModelToLegacyOfferAdapter($this->offer);
    }

    /**
     * @test
     */
    public function it_should_return_an_id()
    {
        $expected = '91060c19-a860-4a47-8591-8a779bfa520a';
        $actual = $this->adapter->getId();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_a_main_language()
    {
        $expected = new \CultuurNet\UDB3\Language('nl');
        $actual = $this->adapter->getMainLanguage();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_a_title()
    {
        $expected = new \CultuurNet\UDB3\Title('Voorbeeld titel');
        $actual = $this->adapter->getTitle();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_a_type()
    {
        $expected = new EventType('0.6.0.0.0', 'Beurs');
        $actual = $this->adapter->getType();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_a_theme()
    {
        $expected = new Theme('0.52.0.0.0', 'Circus');
        $actual = $this->adapter->getTheme();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_as_theme_if_there_is_none()
    {
        $offer = $this->offer->withTerms(
            new Categories(
                new Category(
                    new CategoryID('0.6.0.0.0'),
                    new CategoryLabel('Beurs'),
                    new CategoryDomain('eventtype')
                )
            )
        );
        $adapter = new Udb3ModelToLegacyOfferAdapter($offer);

        $actual = $adapter->getTheme();
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_a_calendar()
    {
        $expected = new Calendar(CalendarType::PERMANENT());
        $actual = $this->adapter->getCalendar();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_an_available_from()
    {
        $expected = \DateTimeImmutable::createFromFormat(\DATE_ATOM, '2018-01-01T10:00:00+01:00');
        $actual = $this->adapter->getAvailableFrom();
        $this->assertEquals($expected, $actual);
    }

    /**
     * @test
     */
    public function it_should_return_null_as_available_from_if_there_is_none()
    {
        $offer = $this->offer->withoutAvailableFrom();
        $adapter = new Udb3ModelToLegacyOfferAdapter($offer);

        $actual = $adapter->getAvailableFrom();
        $this->assertNull($actual);
    }

    /**
     * @test
     */
    public function it_should_return_the_title_translations()
    {
        $expected = [
            'fr' => new \CultuurNet\UDB3\Title('Titre example'),
            'en' => new \CultuurNet\UDB3\Title('Example title'),
        ];
        $actual = $this->adapter->getTitleTranslations();
        $this->assertEquals($expected, $actual);
    }
}
