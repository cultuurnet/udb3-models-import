<?php

namespace CultuurNet\UDB3\Import\MediaObject;

use CultuurNet\UDB3\Language;
use CultuurNet\UDB3\Media\Image;
use CultuurNet\UDB3\Media\ImageCollection;
use CultuurNet\UDB3\Media\MediaManagerInterface;
use CultuurNet\UDB3\Media\Properties\CopyrightHolder;
use CultuurNet\UDB3\Media\Properties\Description;
use CultuurNet\UDB3\Model\ValueObject\MediaObject\MediaObjectReference;
use CultuurNet\UDB3\Model\ValueObject\MediaObject\MediaObjectReferences;
use CultuurNet\UDB3\Model\ValueObject\MediaObject\MediaObjectType;
use ValueObjects\Identity\UUID;

class MediaManagerImageCollectionFactory implements ImageCollectionFactory
{
    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    public function __construct(MediaManagerInterface $mediaManager)
    {
        $this->mediaManager = $mediaManager;
    }

    /**
     * @inheritdoc
     */
    public function fromMediaObjectReferences(MediaObjectReferences $mediaObjectReferences)
    {
        $mediaObjectsReferences = $mediaObjectReferences->filter(
            function (MediaObjectReference $mediaObjectReference) {
                $embeddedMediaObject = $mediaObjectReference->getEmbeddedMediaObject();

                return is_null($embeddedMediaObject) ||
                    $embeddedMediaObject->getType()->sameAs(MediaObjectType::imageObject());
            }
        );

        $images = array_map(
            function (MediaObjectReference $mediaObjectReference) {
                $id = $mediaObjectReference->getMediaObjectId();

                $mediaObjectAggregate = $this->mediaManager->get(new UUID($id->toString()));

                return new Image(
                    $mediaObjectAggregate->getMediaObjectId(),
                    $mediaObjectAggregate->getMimeType(),
                    new Description($mediaObjectReference->getDescription()->toString()),
                    new CopyrightHolder($mediaObjectReference->getCopyrightHolder()->toString()),
                    $mediaObjectAggregate->getSourceLocation(),
                    new Language($mediaObjectReference->getLanguage()->toString())
                );
            },
            $mediaObjectsReferences->toArray()
        );

        return ImageCollection::fromArray($images);
    }
}
