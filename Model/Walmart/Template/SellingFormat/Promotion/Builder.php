<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

namespace Ess\M2ePro\Model\Walmart\Template\SellingFormat\Promotion;

/**
 * Class Ess\M2ePro\Model\Walmart\Template\SellingFormat\Promotion\Builder
 */
class Builder extends \Ess\M2ePro\Model\ActiveRecord\AbstractBuilder
{
    private $templateSellingFormatId;

    /** @var \Ess\M2ePro\Helper\Data */
    protected $helperData;

    public function __construct(
        \Ess\M2ePro\Helper\Data $helperData,
        \Ess\M2ePro\Helper\Factory $helperFactory,
        \Ess\M2ePro\Model\Factory $modelFactory,
        array $data = []
    ) {
        $this->helperData = $helperData;
        parent::__construct($helperFactory, $modelFactory, $data);
    }

    //########################################

    public function setTemplateSellingFormatId($templateSellingFormatId)
    {
        $this->templateSellingFormatId = $templateSellingFormatId;
    }

    public function getTemplateSellingFormatId()
    {
        if (empty($this->templateSellingFormatId)) {
            throw new \Ess\M2ePro\Model\Exception\Logic('templateSellingFormatId not set');
        }

        return $this->templateSellingFormatId;
    }

    //########################################

    protected function prepareData()
    {
        if (!empty($this->rawData['from_date']['value'])) {
            $startDate = $this->helperData
                ->createGmtDateTime($this->rawData['from_date']['value'])
                ->format('Y-m-d H:i');
        } else {
            $startDate = $this->helperData
                ->createCurrentGmtDateTime()
                ->format('Y-m-d H:i');
        }

        if (!empty($this->rawData['to_date']['value'])) {
            $endDate = $this->helperData
                ->createGmtDateTime($this->rawData['to_date']['value'])
                ->format('Y-m-d H:i');
        } else {
            $endDate = $this->helperData
                ->createCurrentGmtDateTime()
                ->format('Y-m-d H:i');
        }

        return [
            'template_selling_format_id'   => $this->getTemplateSellingFormatId(),
            'price_mode'                   => $this->rawData['price']['mode'],
            'price_attribute'              => $this->rawData['price']['attribute'],
            'price_coefficient'            => $this->rawData['price']['coefficient'],
            'start_date_mode'              => $this->rawData['from_date']['mode'],
            'start_date_attribute'         => $this->rawData['from_date']['attribute'],
            'start_date_value'             => $startDate,
            'end_date_mode'                => $this->rawData['to_date']['mode'],
            'end_date_attribute'           => $this->rawData['to_date']['attribute'],
            'end_date_value'               => $endDate,
            'comparison_price_mode'        => $this->rawData['comparison_price']['mode'],
            'comparison_price_attribute'   => $this->rawData['comparison_price']['attribute'],
            'comparison_price_coefficient' => $this->rawData['comparison_price']['coefficient'],
            'type'                         => $this->rawData['type'],
        ];
    }

    public function getDefaultData()
    {
        return [];
    }

    //########################################
}
