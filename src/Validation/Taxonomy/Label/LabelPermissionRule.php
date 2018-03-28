<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Label;

use CultuurNet\UDB3\Label\ReadModels\JSON\Repository\ReadRepositoryInterface as LabelsRepository;
use CultuurNet\UDB3\Label\ReadModels\Relations\Repository\ReadRepositoryInterface as LabelRelationsRepository;
use CultuurNet\UDB3\Security\UserIdentificationInterface;
use Respect\Validation\Rules\AbstractRule;
use ValueObjects\StringLiteral\StringLiteral;

class LabelPermissionRule extends AbstractRule
{
    /**
     * @var string
     */
    private $documentId;

    /**
     * @var UserIdentificationInterface
     */
    private $userIdentification;

    /**
     * @var LabelsRepository
     */
    private $labelsRepository;

    /**
     * @var LabelRelationsRepository
     */
    private $labelRelationsRepository;

    /**
     * @param StringLiteral $documentId
     * @param UserIdentificationInterface $userIdentification
     * @param LabelsRepository $labelsRepository
     * @param LabelRelationsRepository $labelsRelationsRepository
     */
    public function __construct(
        StringLiteral $documentId,
        UserIdentificationInterface $userIdentification,
        LabelsRepository $labelsRepository,
        LabelRelationsRepository $labelsRelationsRepository
    ) {
        $this->documentId = $documentId;
        $this->userIdentification = $userIdentification;
        $this->labelsRepository = $labelsRepository;
        $this->labelRelationsRepository = $labelsRelationsRepository;
    }

    /**
     * @param string $input
     * @return bool
     */
    public function validate($input)
    {
        // A god user can use every label.
        if ($this->userIdentification->isGodUser()) {
            return true;
        }

        // If the label is already present on the item no permission check is needed.
        $labelRelations = $this->labelRelationsRepository->getLabelRelationsForItem($this->documentId);
        foreach ($labelRelations as $labelRelation) {
            if ($labelRelation->getLabelName()->toNative() === $input) {
                return true;
            }
        }

        // The label is not yet present on the item, do a permission check for the active user.
        return $this->labelsRepository->canUseLabel(
            $this->userIdentification->getId(),
            new StringLiteral($input)
        );
    }

    /**
     * @inheritdoc
     */
    protected function createException()
    {
        return new LabelPermissionRuleException();
    }
}
