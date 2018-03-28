<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Organizer;

use CultuurNet\UDB3\Label\ReadModels\JSON\Repository\ReadRepositoryInterface as LabelsRepository;
use CultuurNet\UDB3\Label\ReadModels\Relations\Repository\ReadRepositoryInterface as LabelRelationsRepository;
use CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Label\LabelPermissionRule;
use CultuurNet\UDB3\Model\Organizer\OrganizerIDParser;
use CultuurNet\UDB3\Model\Validation\DocumentValidatorFactory;
use CultuurNet\UDB3\Model\Validation\Organizer\OrganizerValidator;
use CultuurNet\UDB3\Organizer\WebsiteLookupServiceInterface;
use CultuurNet\UDB3\Security\UserIdentificationInterface;
use Respect\Validation\Rules\Key;
use Respect\Validation\Validator;
use ValueObjects\StringLiteral\StringLiteral;

class OrganizerValidatorFactory implements DocumentValidatorFactory
{
    /**
     * @var WebsiteLookupServiceInterface
     */
    private $websiteLookupService;

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
     * @param WebsiteLookupServiceInterface $websiteLookupService
     * @param UserIdentificationInterface $userIdentification
     * @param LabelsRepository $labelsRepository
     * @param LabelRelationsRepository $labelRelationsRepository
     */
    public function __construct(
        WebsiteLookupServiceInterface $websiteLookupService,
        UserIdentificationInterface $userIdentification,
        LabelsRepository $labelsRepository,
        LabelRelationsRepository $labelRelationsRepository
    ) {
        $this->websiteLookupService = $websiteLookupService;
        $this->userIdentification = $userIdentification;
        $this->labelsRepository = $labelsRepository;
        $this->labelRelationsRepository = $labelRelationsRepository;
    }

    /**
     * @param string $id
     * @return Validator
     */
    public function forDocumentId($id)
    {
        $extraRules = [
            new OrganizerHasUniqueUrlValidator(
                new OrganizerIDParser(),
                $this->websiteLookupService
            ),
            new Key(
                'labels',
                new LabelPermissionRule(
                    new StringLiteral($id),
                    $this->userIdentification,
                    $this->labelsRepository,
                    $this->labelRelationsRepository
                ),
                false
            ),
            new Key(
                'hiddenLabels',
                new LabelPermissionRule(
                    new StringLiteral($id),
                    $this->userIdentification,
                    $this->labelsRepository,
                    $this->labelRelationsRepository
                ),
                false
            ),
        ];

        return new OrganizerValidator($extraRules, true);
    }
}
