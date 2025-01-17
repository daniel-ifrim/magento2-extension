<?php

namespace Ess\M2ePro\Model\ControlPanel\Inspection\Inspector;

use Ess\M2ePro\Model\ControlPanel\Inspection\InspectorInterface;

class MagentoSettings implements InspectorInterface
{
    /** @var \Ess\M2ePro\Helper\Client\Cache */
    private $cacheHelper;

    /** @var \Ess\M2ePro\Helper\Data */
    private $dataHelper;

    /** @var \Ess\M2ePro\Model\ControlPanel\Inspection\Issue\Factory */
    private $issueFactory;

    public function __construct(
        \Ess\M2ePro\Helper\Client\Cache $cacheHelper,
        \Ess\M2ePro\Helper\Data $dataHelper,
        \Ess\M2ePro\Model\ControlPanel\Inspection\Issue\Factory $issueFactory
    ) {
        $this->cacheHelper = $cacheHelper;
        $this->dataHelper = $dataHelper;
        $this->issueFactory = $issueFactory;
    }

    //########################################

    public function process()
    {
        $issues = [];

        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            $issues[] = $this->issueFactory->create(
                'GD library is not installed.'
            );
        }

        if ($this->dataHelper->getDefaultTimeZone() !== 'UTC') {
            $issues[] = $this->issueFactory->create(
                'Non-default Magento timezone set.',
                $this->dataHelper->getDefaultTimeZone()
            );
        }

        if ($this->cacheHelper->isApcAvailable()) {
            $issues[] = $this->issueFactory->create(
                'APC Cache is enabled.'
            );
        }

        if ($this->cacheHelper->isMemchachedAvailable()) {
            $issues[] = $this->issueFactory->create(
                'Memchached Cache is enabled.'
            );
        }

        if ($this->cacheHelper->isRedisAvailable()) {
            $issues[] = $this->issueFactory->create(
                'Redis Cache is enabled.'
            );
        }

        return $issues;
    }

    //########################################
}
