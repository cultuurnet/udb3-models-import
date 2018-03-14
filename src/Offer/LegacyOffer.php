<?php

namespace CultuurNet\UDB3\Model\Import\Offer;

use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Theme;
use CultuurNet\UDB3\Title;

interface LegacyOffer
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @return Language
     */
    public function getMainLanguage();

    /**
     * @return Title
     */
    public function getTitle();

    /**
     * @return EventType
     */
    public function getType();

    /**
     * @return Theme|null
     */
    public function getTheme();

    /**
     * @return Calendar
     */
    public function getCalendar();

    /**
     * @param \DateTimeImmutable|null $default
     * @return \DateTimeImmutable|null
     */
    public function getAvailableFrom(\DateTimeImmutable $default = null);

    /**
     * @return Title[]
     *   Language code as key, and Title as value.
     */
    public function getTitleTranslations();
}
