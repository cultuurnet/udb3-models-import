<?php

namespace CultuurNet\UDB3\Model\Import\Organizer;

use CultuurNet\UDB3\LabelCollection;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Title;
use ValueObjects\Web\Url;

interface LegacyOrganizer
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
     * @return Url
     */
    public function getWebsite();

    /**
     * @return Title[]
     *   Language code as key, and Title as value.
     */
    public function getTitleTranslations();

    /**
     * @return LabelCollection
     */
    public function getLabels();
}
