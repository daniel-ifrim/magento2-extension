<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

namespace Ess\M2ePro\Helper\Module;

/**
 * Class \Ess\M2ePro\Helper\Module\Logger
 */
class Logger extends \Ess\M2ePro\Helper\AbstractHelper
{
    protected $modelFactory;
    protected $logSystemFactory;
    protected $phpEnvironmentRequest;

    //########################################

    public function __construct(
        \Ess\M2ePro\Model\Factory $modelFactory,
        \Ess\M2ePro\Model\Log\SystemFactory $logSystemFactory,
        \Ess\M2ePro\Helper\Factory $helperFactory,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\HTTP\PhpEnvironment\Request $phpEnvironmentRequest
    ) {
        $this->modelFactory = $modelFactory;
        $this->logSystemFactory = $logSystemFactory;
        $this->phpEnvironmentRequest = $phpEnvironmentRequest;
        parent::__construct($helperFactory, $context);
    }

    //########################################

    public function process($logData, $class = 'undefined')
    {
        try {
            $info  = $this->getLogMessage($logData, $class);
            $info .= $this->getStackTraceInfo();

            $this->systemLog($class, null, $info);
        } catch (\Exception $exceptionTemp) {
        }
    }

    //########################################

    private function systemLog($class, $message, $description)
    {
        /** @var \Ess\M2ePro\Model\Log\System $log */
        $log = $this->logSystemFactory->create();
        $log->setData(
            [
                'type'                 => \Ess\M2ePro\Model\Log\System::TYPE_LOGGER,
                'class'                => $class,
                'description'          => $message,
                'detailed_description' => $description,
            ]
        );
        $log->save();
    }

    private function getLogMessage($logData, $type)
    {
        // @codingStandardsIgnoreLine
        !is_string($logData) && $logData = print_r($logData, true);

        // @codingStandardsIgnoreLine
        $logData = '[DATE] '.date('Y-m-d H:i:s', (int)gmdate('U')).PHP_EOL.
            '[TYPE] '.$type.PHP_EOL.
            '[MESSAGE] '.$logData.PHP_EOL.
            str_repeat('#', 80).PHP_EOL.PHP_EOL;

        return $logData;
    }

    private function getStackTraceInfo()
    {
        $exception = new \Exception('');
        $stackTraceInfo = <<<TRACE
-------------------------------- STACK TRACE INFO --------------------------------
{$exception->getTraceAsString()}

TRACE;

        return $stackTraceInfo;
    }

    //########################################
}
