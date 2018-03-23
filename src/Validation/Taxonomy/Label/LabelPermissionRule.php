<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Label;

use CultuurNet\UDB3\Label\ReadModels\JSON\Repository\ReadRepositoryInterface;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\Label;
use CultuurNet\UDB3\Security\UserIdentificationInterface;
use Respect\Validation\Rules\AbstractRule;
use ValueObjects\StringLiteral\StringLiteral;

class LabelPermissionRule extends AbstractRule
{
    /**
     * @var UserIdentificationInterface
     */
    private $userIdentification;

    /**
     * @var ReadRepositoryInterface
     */
    private $labelRepository;

    /**
     * @param UserIdentificationInterface $userIdentification
     * @param ReadRepositoryInterface $labelRepository
     */
    public function __construct(
        UserIdentificationInterface $userIdentification,
        ReadRepositoryInterface $labelRepository
    ) {
        $this->userIdentification = $userIdentification;
        $this->labelRepository = $labelRepository;
    }

    /**
     * @param Label $input
     * @return bool
     */
    public function validate($input)
    {
        if (!($input instanceof Label)) {
            return false;
        }

        if ($this->userIdentification->isGodUser()) {
            return true;
        }

        return $this->labelRepository->canUseLabel(
            $this->userIdentification->getId(),
            new StringLiteral($input->getName()->toString())
        );
    }
}
