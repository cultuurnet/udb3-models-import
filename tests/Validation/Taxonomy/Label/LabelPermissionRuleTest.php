<?php

namespace CultuurNet\UDB3\Model\Import\Validation\Taxonomy\Label;

use CultuurNet\UDB3\Label\ReadModels\JSON\Repository\ReadRepositoryInterface;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\Label;
use CultuurNet\UDB3\Model\ValueObject\Taxonomy\Label\LabelName;
use CultuurNet\UDB3\Security\UserIdentificationInterface;
use ValueObjects\StringLiteral\StringLiteral;

class LabelPermissionRuleTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UserIdentificationInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $userIdentification;

    /**
     * @var ReadRepositoryInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $labelRepository;

    /**
     * @ver LabelPermissionRule
     */
    private $labelPermissionRule;

    protected function setUp()
    {
        $this->userIdentification = $this->createMock(UserIdentificationInterface::class);

        $this->labelRepository = $this->createMock(ReadRepositoryInterface::class);

        $this->labelPermissionRule = new LabelPermissionRule(
            $this->userIdentification,
            $this->labelRepository
        );
    }

    /**
     * @test
     */
    public function it_can_only_validate_for_label_instances()
    {
        $this->userIdentification->expects($this->never())
            ->method('isGodUser');

        $this->labelRepository->expects($this->never())
            ->method('canUseLabel');

        $this->assertFalse(
            $this->labelPermissionRule->validate(new StringLiteral('foo'))
        );
    }

    /**
     * @test
     */
    public function a_god_user_can_use_all_labels()
    {
        $this->userIdentification->expects($this->once())
            ->method('isGodUser')
            ->willReturn(true);

        $this->labelRepository->expects($this->never())
            ->method('canUseLabel');

        $this->assertTrue(
            $this->labelPermissionRule->validate(
                new Label(new LabelName('foo'), true)
            )
        );
    }

    /**
     * @test
     */
    public function it_delegates_validation_to_label_repository_for_non_god_user()
    {
        $userId = new StringLiteral('user_id');

        $this->userIdentification->expects($this->once())
            ->method('isGodUser')
            ->willReturn(false);

        $this->userIdentification->expects($this->once())
            ->method('getId')
            ->willReturn($userId);

        $this->labelRepository->expects($this->once())
            ->method('canUseLabel')
            ->with($userId, new StringLiteral('foo'))
            ->willReturn(true);

        $this->assertTrue(
            $this->labelPermissionRule->validate(
                new Label(new LabelName('foo'), true)
            )
        );
    }
}
