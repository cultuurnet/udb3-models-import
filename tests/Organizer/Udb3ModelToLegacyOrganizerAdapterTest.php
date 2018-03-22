<?php

namespace CultuurNet\UDB3\Model\Import\Offer;

use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\Model\Import\Organizer\Udb3ModelToLegacyOrganizerAdapter;
use CultuurNet\UDB3\Model\Organizer\ImmutableOrganizer;
use CultuurNet\UDB3\Model\ValueObject\Identity\UUID;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\Label;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\LabelName;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\Labels;
use CultuurNet\UDB3\Model\ValueObject\Text\Title;
use CultuurNet\UDB3\Model\ValueObject\Text\TranslatedTitle;
use CultuurNet\UDB3\Model\ValueObject\Translation\Language;
use CultuurNet\UDB3\Model\ValueObject\Web\Url;
use PHPUnit\Framework\TestCase;

class Udb3ModelToLegacyOrganizerAdapterTest extends TestCase
{
    /**
     * @var ImmutableOrganizer
     */
    private $organizer;

    /**
     * @var Udb3ModelToLegacyOrganizerAdapter
     */
    private $adapter;

    public function setUp()
    {
        $this->organizer = new ImmutableOrganizer(
            new UUID('91060c19-a860-4a47-8591-8a779bfa520a'),
            new Language('nl'),
            (new TranslatedTitle(new Language('nl'), new Title('Voorbeeld titel')))
                ->withTranslation(new Language('fr'), new Title('Titre example'))
                ->withTranslation(new Language('en'), new Title('Example title')),
            new Url('https://www.publiq.be')
        );

        $this->organizer = $this->organizer->withLabels(
            new Labels(
                new Label(new LabelName('foo'), true),
                new Label(new LabelName('bar'), false)
            )
        );

        $this->adapter = new Udb3ModelToLegacyOrganizerAdapter($this->organizer);
    }

    /**
     * @test
     */
    public function it_should_throw_an_exception_if_the_given_organizer_has_no_url()
    {
        $organizer = new ImmutableOrganizer(
            new UUID('91060c19-a860-4a47-8591-8a779bfa520a'),
            new Language('nl'),
            (new TranslatedTitle(new Language('nl'), new Title('Voorbeeld titel')))
                ->withTranslation(new Language('fr'), new Title('Titre example'))
                ->withTranslation(new Language('en'), new Title('Example title'))
        );

        $this->expectException(\InvalidArgumentException::class);

        new Udb3ModelToLegacyOrganizerAdapter($organizer);
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
    public function it_should_return_a_website()
    {
        $expected = \ValueObjects\Web\Url::fromNative('https://www.publiq.be');
        $actual = $this->adapter->getWebsite();
        $this->assertEquals($expected, $actual);
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

    /**
     * @test
     */
    public function it_should_return_a_label_collection()
    {
        $expected = new LabelCollection(
            [
                new \CultuurNet\UDB3\Label('foo', true),
                new \CultuurNet\UDB3\Label('bar', false),
            ]
        );

        $actual = $this->adapter->getLabels();
        $this->assertEquals($expected, $actual);
    }
}
