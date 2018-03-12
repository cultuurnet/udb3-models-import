<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Category;

use CultuurNet\UDB3\Model\Import\Event\EventLegacyBridgeCategoryResolver;
use CultuurNet\UDB3\Model\Import\Taxonomy\Category\EventTypeCountValidator;
use PHPUnit\Framework\TestCase;
use Respect\Validation\Exceptions\GroupedValidationException;

class EventTypeValidatorTest extends TestCase
{
    /**
     * @var EventTypeCountValidator
     */
    private $validator;

    public function setUp()
    {
        $this->validator = new EventTypeCountValidator();
    }

    /**
     * @test
     */
    public function it_should_pass_if_the_categories_contain_exactly_one_eventtype()
    {
        $categories = [
            [
                'id' => '1.2.1.0.0',
                'label' => 'Architectuur',
                'domain' => 'theme',
            ],
            [
                'id' => '0.54.0.0.0',
                'label' => 'Dansvoorstelling',
                'domain' => 'eventtype',
            ],
            [
                'id' => '3.23.2.0.0',
                'label' => 'Assistentie',
                'domain' => 'facility',
            ],
        ];

        $this->assertTrue($this->validator->validate($categories));
    }

    /**
     * @test
     */
    public function it_should_throw_an_exception_the_categories_have_more_than_one_eventtype()
    {
        $categories = [
            [
                'id' => '1.2.1.0.0',
                'label' => 'Architectuur',
                'domain' => 'theme',
            ],
            [
                'id' => '0.54.0.0.0',
                'label' => 'Dansvoorstelling',
                'domain' => 'eventtype',
            ],
            [
                'id' => '3.23.2.0.0',
                'label' => 'Assistentie',
                'domain' => 'facility',
            ],
            [
                'id' => '0.6.0.0.0',
                'label' => 'Beurs',
                'domain' => 'eventtype',
            ],
        ];

        $expected = [
            'terms must contain at exactly one item with domain "eventtype".',
        ];

        try {
            $this->validator->assert($categories);
            $errors = [];
        } catch (GroupedValidationException $e) {
            $errors = $e->getMessages();
        }

        $this->assertEquals($expected, $errors);
    }

    /**
     * @test
     */
    public function it_should_throw_an_exception_the_categories_have_no_eventtype()
    {
        $categories = [
            [
                'id' => '1.2.1.0.0',
                'label' => 'Architectuur',
                'domain' => 'theme',
            ],
            [
                'id' => '3.23.2.0.0',
                'label' => 'Assistentie',
                'domain' => 'facility',
            ],
        ];

        $expected = [
            'terms must contain exactly one item with domain "eventtype".',
        ];

        try {
            $this->validator->assert($categories);
            $errors = [];
        } catch (GroupedValidationException $e) {
            $errors = $e->getMessages();
        }

        $this->assertEquals($expected, $errors);
    }
}
