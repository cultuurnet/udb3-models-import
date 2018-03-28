<?php

namespace CultuurNet\UDB3\Model\Import\PreProcessing;

use CultuurNet\UDB3\Media\MediaManagerInterface;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use CultuurNet\UDB3\Model\ValueObject\Identity\UUIDParser;
use CultuurNet\UDB3\Model\ValueObject\Web\Url;
use Symfony\Component\Serializer\SerializerInterface;
use ValueObjects\Identity\UUID;

class MediaObjectPreProcessingDocumentImporter implements DocumentImporterInterface
{
    /**
     * @var DocumentImporterInterface
     */
    private $jsonImporter;

    /**
     * @var UUIDParser
     */
    private $mediaObjectIdParser;

    /**
     * @var MediaManagerInterface
     */
    private $mediaManager;

    /**
     * @var SerializerInterface|null
     */
    private $mediaObjectSerializer;

    /**
     * @param DocumentImporterInterface $jsonImporter
     * @param UUIDParser $mediaObjectIdParser
     * @param MediaManagerInterface $mediaManager
     * @param SerializerInterface|null $mediaObjectSerializer
     *   Usually an instance of CultuurNet\UDB3\Media\Serialization\MediaObjectSerializer.
     */
    public function __construct(
        DocumentImporterInterface $jsonImporter,
        UUIDParser $mediaObjectIdParser,
        MediaManagerInterface $mediaManager,
        SerializerInterface $mediaObjectSerializer
    ) {
        $this->jsonImporter = $jsonImporter;
        $this->mediaObjectIdParser = $mediaObjectIdParser;
        $this->mediaManager = $mediaManager;
        $this->mediaObjectSerializer = $mediaObjectSerializer;
    }

    /**
     * Pre-processes the JSON to polyfill missing mediaObject properties if possible.
     *
     * @param DecodedDocument $decodedDocument
     */
    public function import(DecodedDocument $decodedDocument)
    {
        $data = $decodedDocument->getBody();

        // Attempt to add @type and/or contentUrl and/or thumbUrl, or fix them if they're incorrect.
        if (isset($data['mediaObject']) && is_array($data['mediaObject'])) {
            $data['mediaObject'] = array_map(
                function (array $mediaObjectData) {
                    if (isset($mediaObjectData['@id']) && is_string($mediaObjectData['@id'])) {
                        try {
                            $idUrl = new Url($mediaObjectData['@id']);
                            $id = $this->mediaObjectIdParser->fromUrl($idUrl);
                            $mediaObject = $this->mediaManager->get(new UUID($id));
                            $mediaObjectData = $this->mediaObjectSerializer->serialize($mediaObject, 'json');

                            // Fail-safe in case the MediaObjectSerializer gets
                            // refactored to comply with the Serializer
                            // interface and return an encoded string instead
                            // of an array.
                            if (is_string($mediaObjectData)) {
                                $mediaObjectData = json_decode($mediaObjectData, true);
                            }
                        } catch (\Exception $e) {
                            // Will be handled by the validators.
                        }
                    }

                    return $mediaObjectData;
                },
                $data['mediaObject']
            );
        }

        $decodedDocument = $decodedDocument->withBody($data);

        $this->jsonImporter->import($decodedDocument);
    }
}
