<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

namespace Ess\M2ePro\Controller\Adminhtml\ControlPanel\Debug;

use Ess\M2ePro\Controller\Adminhtml\ControlPanel\Command;

class Debug extends Command
{
    public function __construct(
        \Ess\M2ePro\Helper\View\ControlPanel $controlPanelHelper,
        \Ess\M2ePro\Controller\Adminhtml\Context $context
    ) {
        parent::__construct($controlPanelHelper, $context);
    }

    /**
     * @title "First Test"
     * @description "Command for quick development"
     */
    public function firstTestAction()
    {
        return null;
    }

    /**
     * @title "Second Test"
     * @description "Command for quick development"
     */
    public function secondTestAction()
    {
        return null;
    }
}
