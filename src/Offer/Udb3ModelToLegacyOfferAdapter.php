<?php

namespace CultuurNet\UDB3\Model\Import\Offer;

use CultuurNet\UDB3\Calendar;
use CultuurNet\UDB3\Event\EventType;
use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Model\Offer\Offer;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\Category;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Category\CategoryDomain;
use CultuurNet\UDB3\Theme;
use CultuurNet\UDB3\Title;

class Udb3ModelToLegacyOfferAdapter implements LegacyOffer
{
    /**
     * @var Offer
     */
    private $offer;

    /**
     * @param Offer $offer
     */
    public function __construct(Offer $offer)
    {
        $this->offer = $offer;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->offer->getId()->toString();
    }

    /**
     * @inheritdoc
     */
    public function getMainLanguage()
    {
        return Language::fromUdb3ModelLanguage(
            $this->offer->getMainLanguage()
        );
    }

    /**
     * @inheritdoc
     */
    public function getTitle()
    {
        $translatedTitle = $this->offer->getTitle();

        return Title::fromUdb3ModelTitle(
            $translatedTitle->getTranslation(
                $translatedTitle->getOriginalLanguage()
            )
        );
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        $type = $this->offer->getTerms()
            ->filter(
                function (Category $term) {
                    $domain = $term->getDomain();
                    return $domain && $domain->sameAs(new CategoryDomain('eventtype'));
                }
            )
            ->getFirst();

        return EventType::fromUdb3ModelCategory($type);
    }

    /**
     * @inheritdoc
     */
    public function getTheme()
    {
        $theme = $this->offer->getTerms()
            ->filter(
                function (Category $term) {
                    $domain = $term->getDomain();
                    return $domain && $domain->sameAs(new CategoryDomain('theme'));
                }
            )
            ->getFirst();

        return $theme ? Theme::fromUdb3ModelCategory($theme) : null;
    }

    /**
     * @inheritdoc
     */
    public function getCalendar()
    {
        return Calendar::fromUdb3ModelCalendar($this->offer->getCalendar());
    }

    /**
     * @param \DateTimeImmutable|null $default
     * @return \DateTimeImmutable|null
     */
    public function getAvailableFrom(\DateTimeImmutable $default = null)
    {
        $availableFrom = $this->offer->getAvailableFrom();
        if (!$availableFrom) {
            $availableFrom = $default;
        }
        return $availableFrom;
    }

    /**
     * @return Title[]
     *   Language code as key, and Title as value.
     */
    public function getTitleTranslations()
    {
        $titles = [];

        /* @var \CultuurNet\UDB3\Model\ValueObject\Translation\Language $language */
        $translatedTitle = $this->offer->getTitle();
        foreach ($translatedTitle->getLanguagesWithoutOriginal() as $language) {
            $titles[$language->toString()] = Title::fromUdb3ModelTitle(
                $translatedTitle->getTranslation($language)
            );
        }

        return $titles;
    }
}
