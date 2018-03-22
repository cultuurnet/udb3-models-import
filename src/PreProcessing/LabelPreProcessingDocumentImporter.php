<?php

namespace CultuurNet\UDB3\Model\Import\PreProcessing;

use CultuurNet\UDB3\Label\ReadModels\JSON\Repository\ReadRepositoryInterface as LabelRepository;
use CultuurNet\UDB3\Label\ReadModels\Relations\Repository\LabelRelation;
use CultuurNet\UDB3\Label\ReadModels\Relations\Repository\ReadRepositoryInterface as LabelRelationsRepository;
use CultuurNet\UDB3\Label\ValueObjects\Visibility;
use CultuurNet\UDB3\Model\Import\DecodedDocument;
use CultuurNet\UDB3\Model\Import\DocumentImporterInterface;
use ValueObjects\StringLiteral\StringLiteral;

class LabelPreProcessingDocumentImporter implements DocumentImporterInterface
{
    /**
     * @var DocumentImporterInterface
     */
    private $jsonImporter;

    /**
     * @var LabelRepository
     */
    private $labelsRepository;

    /**
     * @var LabelRelationsRepository
     */
    private $labelRelationsRepository;

    /**
     * @param DocumentImporterInterface $jsonImporter
     * @param LabelRepository $labelsRepository
     * @param LabelRelationsRepository $labelRelationsRepository
     */
    public function __construct(
        DocumentImporterInterface $jsonImporter,
        LabelRepository $labelsRepository,
        LabelRelationsRepository $labelRelationsRepository
    ) {
        $this->jsonImporter = $jsonImporter;
        $this->labelsRepository = $labelsRepository;
        $this->labelRelationsRepository = $labelRelationsRepository;
    }

    /**
     * @param DecodedDocument $decodedDocument
     */
    public function import(DecodedDocument $decodedDocument)
    {
        $data = $decodedDocument->getBody();
        $id = $decodedDocument->getId();

        // Approach is to:
        //  1. get all UDB3 labels from label relation (hidden and visible)
        //  2. remove all UDB3 labels from document (both hidden and visible)
        //  3. add all UDB3 labels to document (both hidden and visible)
        // By using this approach the correct visible/invisible label state
        // is taken into account and the JSON document is correct.

        // 1.
        /** @var LabelRelation[] $udb3LabelRelations */
        $udb3LabelRelations = array_filter(
            $this->labelRelationsRepository->getLabelRelationsForItem(
                new StringLiteral($id)
            ),
            function (LabelRelation $labelRelation) {
                return !$labelRelation->isImported();
            }
        );
        $udb3Labels = array_map(
            function (LabelRelation $labelRelation) {
                return $labelRelation->getLabelName()->toNative();
            },
            $udb3LabelRelations
        );

        // 2.
        $data['labels'] = array_diff(
            $data['labels'],
            $udb3Labels
        );
        $data['hiddenLabels'] = array_diff(
            $data['hiddenLabels'],
            $udb3Labels
        );

        // 3.
        foreach ($udb3LabelRelations as $udb3LabelRelation) {
            // @todo: what if inside label relations but not inside label repo?
            $label = $this->labelsRepository->getByName($udb3LabelRelation->getLabelName());
            $labelName = $label->getName()->toNative();

            if ($label->getVisibility()->sameValueAs(Visibility::VISIBLE())) {
                $data['labels'][] = $labelName;
            } else {
                $data['hiddenLabels'][] = $labelName;
            }
        }

        $data['labels'] = array_values($data['labels']);
        $data['hiddenLabels'] = array_values($data['hiddenLabels']);

        $decodedDocument = $decodedDocument->withBody($data);
        $this->jsonImporter->import($decodedDocument);
    }
}
