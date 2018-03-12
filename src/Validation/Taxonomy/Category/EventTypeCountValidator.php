<?php

namespace CultuurNet\UDB3\Model\Import\Taxonomy\Category;

use Respect\Validation\Rules\AlwaysValid;
use Respect\Validation\Rules\ArrayType;
use Respect\Validation\Rules\Callback;
use Respect\Validation\Rules\When;
use Respect\Validation\Validator;

class EventTypeCountValidator extends Validator
{
    public function __construct()
    {
        // Only check that there is at least one "eventtype" term if the
        // categories are in the expected format.
        // Any other errors will be reported by the validators in udb3-models.
        $rules = [
            new When(
                new ArrayType(),
                (new Callback(
                    function (array $categories) {
                        $eventTypes = array_filter(
                            $categories,
                            function (array $category) {
                                return isset($category['domain']) && $category['domain'] === 'eventtype';
                            }
                        );

                        return count($eventTypes) == 1;
                    }
                ))->setTemplate('terms must contain at exactly one item with domain "eventtype".'),
                new AlwaysValid()
            )
        ];

        parent::__construct($rules);
    }
}
