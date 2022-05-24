<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

namespace Ess\M2ePro\Block\Adminhtml\Amazon\Listing\Product\Variation\Manage\Tabs\Variations;

use Ess\M2ePro\Model\Amazon\Listing\Product\Variation\Manager\Type\Relation\ChildRelation;
use Ess\M2ePro\Model\Listing\Log;

/**
 * Class \Ess\M2ePro\Block\Adminhtml\Amazon\Listing\Product\Variation\Manage\Tabs\Variations\Grid
 */
class Grid extends \Ess\M2ePro\Block\Adminhtml\Magento\Grid\AbstractGrid
{
    private $lockedDataCache = [];

    protected $childListingProducts = null;
    protected $currentProductVariations = null;
    protected $usedProductVariations = null;

    /** @var \Ess\M2ePro\Model\Listing\Product $listingProduct */
    protected $listingProduct;

    protected $amazonFactory;
    protected $localeCurrency;
    protected $resourceConnection;

    //########################################

    public function __construct(
        \Ess\M2ePro\Model\ActiveRecord\Component\Parent\Amazon\Factory $amazonFactory,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Ess\M2ePro\Block\Adminhtml\Magento\Context\Template $context,
        \Magento\Backend\Helper\Data $backendHelper,
        array $data = []
    ) {
        $this->amazonFactory = $amazonFactory;
        $this->localeCurrency = $localeCurrency;
        $this->resourceConnection = $resourceConnection;
        parent::__construct($context, $backendHelper, $data);
    }

    //########################################

    public function _construct()
    {
        parent::_construct();

        $this->setId('amazonVariationProductManageGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
    }

    //########################################

    /**
     * @param \Ess\M2ePro\Model\Listing\Product $listingProduct
     */
    public function setListingProduct(\Ess\M2ePro\Model\Listing\Product $listingProduct)
    {
        $this->listingProduct = $listingProduct;
    }

    /**
     * @return \Ess\M2ePro\Model\Listing\Product
     */
    protected function getListingProduct()
    {
        return $this->listingProduct;
    }

    //########################################

    protected function _prepareCollection()
    {
        $collection = $this->amazonFactory->getObject('Listing\Product')->getCollection();
        $collection->getSelect()->distinct();
        $collection->getSelect()->where(
            "`second_table`.`variation_parent_id` = ?",
            (int)$this->getListingProduct()->getId()
        );

        $collection->getSelect()->columns([
            'online_current_price' => new \Zend_Db_Expr('
                IF (
                    `second_table`.`online_regular_price` IS NULL,
                    `second_table`.`online_business_price`,
                    IF (
                        `second_table`.`online_regular_sale_price` IS NOT NULL AND
                        `second_table`.`online_regular_sale_price_end_date` IS NOT NULL AND
                        `second_table`.`online_regular_sale_price_start_date` <= CURRENT_DATE() AND
                        `second_table`.`online_regular_sale_price_end_date` >= CURRENT_DATE(),
                        `second_table`.`online_regular_sale_price`,
                        `second_table`.`online_regular_price`
                    )
                )
            '),
            'amazon_status' => 'status',
            'amazon_sku'    => 'second_table.sku',
        ]);

        $lpvTable = $this->activeRecordFactory->getObject('Listing_Product_Variation')->getResource()->getMainTable();
        $lpvoTable = $this->activeRecordFactory->getObject('Listing_Product_Variation_Option')
            ->getResource()->getMainTable();
        $collection->getSelect()->joinLeft(
            new \Zend_Db_Expr('(
                SELECT
                    mlpv.listing_product_id,
                    GROUP_CONCAT(`mlpvo`.`attribute`, \'==\', `mlpvo`.`product_id` SEPARATOR \'||\') as products_ids
                FROM `'. $lpvTable .'` as mlpv
                INNER JOIN `'. $lpvoTable .
                    '` AS `mlpvo` ON (`mlpvo`.`listing_product_variation_id`=`mlpv`.`id`)
                WHERE `mlpv`.`component_mode` = \'amazon\'
                GROUP BY `mlpv`.`listing_product_id`
            )'),
            'main_table.id=t.listing_product_id',
            [
                'products_ids' => 'products_ids',
            ]
        );

        $alprTable = $this->activeRecordFactory->getObject('Amazon_Listing_Product_Repricing')
            ->getResource()->getMainTable();
        $collection->getSelect()->joinLeft(
            ['malpr' => $alprTable],
            '(`second_table`.`listing_product_id` = `malpr`.`listing_product_id`)',
            [
                'is_repricing_disabled' => 'is_online_disabled',
            ]
        );

        if ($this->getParam($this->getVarNameFilter()) == 'searched_by_child'){
            $collection->addFieldToFilter(
                'second_table.listing_product_id',
                ['in' => explode(',', $this->getRequest()->getParam('listing_product_id_filter'))]
            );
        }

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        /** @var \Ess\M2ePro\Model\Amazon\Listing\Product\Variation\Manager\Type\Relation\ParentRelation $parentType */
        $parentType = $this->getListingProduct()->getChildObject()->getVariationManager()->getTypeModel();

        $channelAttributesSets = $parentType->getChannelAttributesSets();
        $productAttributes = $parentType->getProductAttributes();

        if ($parentType->hasMatchedAttributes()) {
            $productAttributes = array_keys($parentType->getMatchedAttributes());
            $channelAttributes = array_values($parentType->getMatchedAttributes());
        } elseif (!empty($channelAttributesSets)) {
            $channelAttributes = array_keys($channelAttributesSets);
        } else {
            $channelAttributes = [];
        }

        $this->addColumn('product_options', [
            'header'         => $this->__('Magento Variation'),
            'align'          => 'left',
            'width'          => '210px',
            'sortable'       => false,
            'index'          => 'additional_data',
            'options'        => $productAttributes,
            'filter_index'   => 'additional_data',
            'frame_callback' => [$this, 'callbackColumnProductOptions'],
            'filter_condition_callback' => [$this, 'callbackProductOptions'],
            'filter' => 'Ess\M2ePro\Block\Adminhtml\Magento\Grid\Column\Filter\AttributesOptions'
        ]);

        $this->addColumn('channel_options', [
            'header'         => $this->__('Amazon Variation'),
            'align'          => 'left',
            'width'          => '210px',
            'sortable'       => false,
            'index'          => 'additional_data',
            'filter_index'   => 'additional_data',
            'frame_callback' => [$this, 'callbackColumnChannelOptions'],
            'filter'         => 'Ess\M2ePro\Block\Adminhtml\Magento\Grid\Column\Filter\AttributesOptions',
            'options'        => $channelAttributes,
            'filter_condition_callback' => [$this, 'callbackChannelOptions']
        ]);

        $this->addColumn('amazon_sku', [
            'header'       => $this->__('SKU'),
            'align'        => 'left',
            'type'         => 'text',
            'index'        => 'amazon_sku',
            'filter_index' => 'amazon_sku',
            'frame_callback' => [$this, 'callbackColumnAmazonSku'],
            'filter_condition_callback' => [$this, 'callbackFilterSku']
        ]);

        $this->addColumn('general_id', [
            'header'         => $this->__('ASIN / ISBN'),
            'align'          => 'left',
            'width'          => '100px',
            'type'           => 'text',
            'index'          => 'general_id',
            'filter_index'   => 'general_id',
            'frame_callback' => [$this, 'callbackColumnGeneralId']
        ]);

        $this->addColumn('online_qty', [
            'header'              => $this->__('QTY'),
            'align'               => 'right',
            'width'               => '70px',
            'type'                => 'number',
            'index'               => 'online_qty',
            'filter_index'        => 'online_qty',
            'show_receiving'      => false,
            'is_variation_grid'   => true,
            'renderer'            => '\Ess\M2ePro\Block\Adminhtml\Amazon\Grid\Column\Renderer\Qty',
            'filter'              => 'Ess\M2ePro\Block\Adminhtml\Amazon\Grid\Column\Filter\Qty',
            'filter_condition_callback' => [$this, 'callbackFilterQty']
        ]);

        $priceColumn = [
            'header'                    => $this->__('Price'),
            'align'                     => 'right',
            'width'                     => '70px',
            'type'                      => 'number',
            'index'                     => 'online_current_price',
            'filter_index'              => 'online_current_price',
            'is_variation_grid'         => true,
            'marketplace_id'            => $this->getListingProduct()->getListing()->getMarketplaceId(),
            'account_id'                => $this->getListingProduct()->getListing()->getAccountId(),
            'renderer'                  => '\Ess\M2ePro\Block\Adminhtml\Amazon\Grid\Column\Renderer\Price',
            'filter_condition_callback' => [$this, 'callbackFilterPrice']
        ];

        if ($this->getHelper('Component_Amazon_Repricing')->isEnabled() &&
            $this->getListingProduct()->getListing()->getAccount()->getChildObject()->isRepricing()) {
            $priceColumn['filter'] = 'Ess\M2ePro\Block\Adminhtml\Amazon\Grid\Column\Filter\Price';
        }

        $this->addColumn('online_current_price', $priceColumn);

        $this->addColumn('amazon_status', [
            'header'   => $this->__('Status'),
            'width'    => '100px',
            'index'    => 'amazon_status',
            'filter_index' => 'amazon_status',
            'type'     => 'options',
            'sortable' => false,
            'options'  => [
                \Ess\M2ePro\Model\Listing\Product::STATUS_UNKNOWN => $this->__('Unknown'),
                \Ess\M2ePro\Model\Listing\Product::STATUS_NOT_LISTED => $this->__('Not Listed'),
                \Ess\M2ePro\Model\Listing\Product::STATUS_LISTED => $this->__('Active'),
                \Ess\M2ePro\Model\Listing\Product::STATUS_STOPPED => $this->__('Inactive'),
                \Ess\M2ePro\Model\Listing\Product::STATUS_BLOCKED => $this->__('Inactive (Blocked)')
            ],
            'is_variation_grid' => true,
            'renderer' => '\Ess\M2ePro\Block\Adminhtml\Amazon\Grid\Column\Renderer\Status',
            'filter_condition_callback' => [$this, 'callbackFilterStatus']
        ]);

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('id');
        $this->setMassactionIdFieldOnlyIndexValue(true);

        $this->getMassactionBlock()->addItem('list', [
            'label'    => $this->__('List Item(s)'),
            'url'      => ''
        ]);

        $this->getMassactionBlock()->addItem('revise', [
            'label'    => $this->__('Revise Item(s)'),
            'url'      => ''
        ]);

        $this->getMassactionBlock()->addItem('relist', [
            'label'    => $this->__('Relist Item(s)'),
            'url'      => ''
        ]);

        $this->getMassactionBlock()->addItem('stop', [
            'label'    => $this->__('Stop Item(s)'),
            'url'      => ''
        ]);

        $this->getMassactionBlock()->addItem('stopAndRemove', [
            'label'    => $this->__('Stop on Channel / Remove from Listing'),
            'url'      => ''
        ]);

        $this->getMassactionBlock()->addItem('deleteAndRemove', [
            'label'    => $this->__('Remove from Channel & Listing'),
            'url'      => ''
        ]);

        return parent::_prepareMassaction();
    }

    //########################################

    public function callbackColumnProductOptions($additionalData, $row, $column, $isExport)
    {
        $html = '';

        /** @var \Ess\M2ePro\Model\Amazon\Listing\Product\Variation\Manager\Type\Relation\ChildRelation $typeModel */
        $typeModel = $row->getChildObject()->getVariationManager()->getTypeModel();

        $html .= '<div class="product-options-main" style="font-size: 11px; color: grey; margin-left: 7px">';
        $productOptions = $typeModel->getProductOptions();
        if (!empty($productOptions)) {
            $productsIds = $this->parseGroupedData($row->getData('products_ids'));
            $uniqueProductsIds = count(array_unique($productsIds)) > 1;

            $matchedAttributes = $typeModel->getParentTypeModel()->getMatchedAttributes();
            if (!empty($matchedAttributes)) {
                $sortedOptions = [];

                foreach ($matchedAttributes as $magentoAttr => $amazonAttr) {
                    $sortedOptions[$magentoAttr] = $productOptions[$magentoAttr];
                }

                $productOptions = $sortedOptions;
            }

            $virtualProductAttributes = array_keys($typeModel->getParentTypeModel()->getVirtualProductAttributes());

            $html .= '<div class="m2ePro-variation-attributes product-options-list">';
            if (!$uniqueProductsIds) {
                $url = $this->getUrl('catalog/product/edit', ['id' => reset($productsIds)]);
                $html .= '<a href="' . $url . '" target="_blank">';
            }
            foreach ($productOptions as $attribute => $option) {
                $style = '';
                if (in_array($attribute, $virtualProductAttributes, true)) {
                    $style = 'border-bottom: 2px dotted grey';
                }

                if ($option === '' || $option === null) {
                    $option = '--';
                }
                $optionHtml = '<span class="attribute-row" style="' . $style . '"><span class="attribute"><strong>' .
                    $this->getHelper('Data')->escapeHtml($attribute) .
                    '</strong></span>:&nbsp;<span class="value">' . $this->getHelper('Data')->escapeHtml($option) .
                    '</span></span>';

                if ($uniqueProductsIds && $option !== '--' && !in_array($attribute, $virtualProductAttributes, true)) {
                    $url = $this->getUrl('catalog/product/edit', ['id' => $productsIds[$attribute]]);
                    $html .= '<a href="' . $url . '" target="_blank">' . $optionHtml . '</a><br/>';
                } else {
                    $html .= $optionHtml . '<br/>';
                }
            }
            if (!$uniqueProductsIds) {
                $html .= '</a>';
            }
            $html .= '</div>';
        }

        if ($this->canChangeProductVariation($row)) {
            $listingProductId = $row->getId();
            $attributes = array_keys($typeModel->getParentTypeModel()->getMatchedAttributes());
            $variationsTree = $this->getProductVariationsTree($row, $attributes);

            $linkTitle = $this->__('Change Variation');
            $linkContent = $this->__('Change Variation');

            $attributes = $this->getHelper('Data')->escapeHtml($this->getHelper('Data')->jsonEncode($attributes));
            $variationsTree = $this->getHelper('Data')->escapeHtml(
                $this->getHelper('Data')->jsonEncode($variationsTree)
            );

            $html .= <<<HTML
<form action="javascript:void(0);" class="product-options-edit"></form>
<a href="javascript:" style="line-height: 23px;"
    onclick="ListingProductVariationManageVariationsGridObj.editProductOptions(
        this, {$attributes}, {$variationsTree}, {$listingProductId}
    )"
    title="{$linkTitle}">{$linkContent}</a>
HTML;
        }

        $html .= '</div>';

        return $html;
    }

    public function callbackColumnChannelOptions($additionalData, $row, $column, $isExport)
    {
        /** @var \Ess\M2ePro\Model\Amazon\Listing\Product $amazonListingProduct */
        $amazonListingProduct = $row->getChildObject();

        $typeModel = $amazonListingProduct->getVariationManager()->getTypeModel();

        /** @var \Ess\M2ePro\Model\Amazon\Listing\Product $parentAmazonListingProduct */
        $parentAmazonListingProduct = $typeModel->getParentListingProduct()->getChildObject();

        $matchedAttributes = $parentAmazonListingProduct->getVariationManager()
            ->getTypeModel()
            ->getMatchedAttributes();

        if (!$typeModel->isVariationChannelMatched()) {
            if (!$typeModel->isVariationProductMatched() || !$amazonListingProduct->isGeneralIdOwner()) {
                return '';
            }

            if (empty($matchedAttributes)) {
                return '';
            }

            $options = [];

            foreach ($typeModel->getProductOptions() as $attribute => $value) {
                $options[$matchedAttributes[$attribute]] = $value;
            }
        } else {
            $options = $typeModel->getChannelOptions();

            if (!empty($matchedAttributes)) {
                $sortedOptions = [];

                foreach ($matchedAttributes as $magentoAttr => $amazonAttr) {
                    $sortedOptions[$amazonAttr] = $options[$amazonAttr];
                }

                $options = $sortedOptions;
            }
        }

        if (empty($options)) {
            return '';
        }

        $generalId = $amazonListingProduct->getGeneralId();

        $virtualChannelAttributes = array_keys($typeModel->getParentTypeModel()->getVirtualChannelAttributes());

        $html = '<div class="m2ePro-variation-attributes" style="color: grey; margin-left: 7px">';

        if (!empty($generalId)) {
            $url = $this->getHelper('Component\Amazon')->getItemUrl(
                $generalId,
                $this->getListingProduct()->getListing()->getMarketplaceId()
            );

            $html .= '<a href="' . $url . '" target="_blank" title="' . $generalId . '" >';
        }

        foreach ($options as $attribute => $option) {
            $style = '';
            if (in_array($attribute, $virtualChannelAttributes, true)) {
                $style = 'border-bottom: 2px dotted grey';
            }

            if ($option === '' || $option === null) {
                $option = '--';
            }

            $attrName = $this->getHelper('Data')->escapeHtml($attribute);
            $optionName = $this->getHelper('Data')->escapeHtml($option);

            if (empty($generalId) && $amazonListingProduct->isGeneralIdOwner()) {
                $html .= <<<HTML
<span style="{$style}">{$attrName}:&nbsp;{$optionName}</span><br/>
HTML;
            } else {
                $html .= <<<HTML
<span style="{$style}"><b>{$attrName}</b>:&nbsp;{$optionName}</span><br/>
HTML;
            }
        }

        if (!empty($generalId)) {
            $html .= '</a>';
        }

        $html .= '</div>';

        return $html;
    }

    public function callbackColumnAmazonSku($value, $row, $column, $isExport)
    {
        $value = $row->getChildObject()->getData('sku');
        if ($value === null || $value === '') {
            $value = $this->__('N/A');
        }

        if ($row->getChildObject()->getData('defected_messages')) {
            $defectedMessages = $this->getHelper('Data')->jsonDecode(
                $row->getChildObject()->getData('defected_messages')
            );

            $msg = '';
            foreach ($defectedMessages as $message) {
                if (empty($message['message'])) {
                    continue;
                }

                $msg .= '<p>'.$message['message'] . '&nbsp;';
                if (!empty($message['value'])) {
                    $msg .= $this->__('Current Value') . ': "' . $message['value'] . '"';
                }
                $msg .= '</p>';
            }

            if (empty($msg)) {
                return $value;
            }

            $value .= <<<HTML
<span style="float:right;">
    {$this->getTooltipHtml($msg, 'map_link_defected_message_icon_'.$row->getId())}
</span>
HTML;
        }

        return $value;
    }

    public function callbackColumnGeneralId($generalId, $row, $column, $isExport)
    {
        $generalId = $row->getChildObject()->getData('general_id');

        if ($generalId === null || $generalId === '') {

            /** @var \Ess\M2ePro\Model\Amazon\Listing\Product $amazonListingProduct */
            $amazonListingProduct = $this->getListingProduct()->getChildObject();
            if ($amazonListingProduct->isGeneralIdOwner()) {
                return $this->__('New ASIN/ISBN');
            }

            return $this->__('N/A');
        }
        return $this->getGeneralIdLink($generalId);
    }

    public function callbackProductOptions($collection, $column)
    {
        $values = $column->getFilter()->getValue();

        if ($values == null && !is_array($values)) {
            return;
        }

        foreach ($values as $value) {
            if (is_array($value) && isset($value['value'])) {
                $collection->addFieldToFilter(
                    'additional_data',
                    ['regexp'=> '"variation_product_options":[^}]*' .
                        $value['attr'] . '[[:space:]]*":"[[:space:]]*' .
                        // trying to screen slashes that in json
                        addslashes(addslashes($value['value']).'[[:space:]]*')]
                );
            }
        }
    }

    public function callbackChannelOptions($collection, $column)
    {
        $values = $column->getFilter()->getValue();

        if ($values == null && !is_array($values)) {
            return;
        }

        foreach ($values as $value) {
            if (is_array($value) && isset($value['value'])) {
                $collection->addFieldToFilter(
                    'additional_data',
                    ['regexp'=> '"variation_channel_options":[^}]*' .
                        $value['attr'] . '[[:space:]]*":"[[:space:]]*' .
                        // trying to screen slashes that in json
                        addslashes(addslashes($value['value']).'[[:space:]]*')]
                );
            }
        }
    }

    protected function callbackFilterQty($collection, $column)
    {
        $value = $column->getFilter()->getValue();

        if (empty($value)) {
            return;
        }

        $where = '';

        if (isset($value['from']) && $value['from'] != '') {
            $where .= 'online_qty >= ' . (int)$value['from'];
        }

        if (isset($value['to']) && $value['to'] != '') {
            if (isset($value['from']) && $value['from'] != '') {
                $where .= ' AND ';
            }
            $where .= 'online_qty <= ' . (int)$value['to'];
        }

        if (isset($value['afn']) && $value['afn'] !== '') {
            if (!empty($where)) {
                $where = '(' . $where . ') OR ';
            }
            $where .= 'is_afn_channel = ' . (int)$value['afn'];
        }

        $collection->getSelect()->where($where);
    }

    protected function callbackFilterPrice($collection, $column)
    {
        $value = $column->getFilter()->getValue();

        if (empty($value)) {
            return;
        }

        $condition = '';

        if (isset($value['from']) || isset($value['to'])) {
            if (isset($value['from']) && $value['from'] != '') {
                $condition = 'second_table.online_regular_price >= \''.(float)$value['from'].'\'';
            }
            if (isset($value['to']) && $value['to'] != '') {
                if (isset($value['from']) && $value['from'] != '') {
                    $condition .= ' AND ';
                }
                $condition .= 'second_table.online_regular_price <= \''.(float)$value['to'].'\'';
            }

            $condition = '(' . $condition . ' AND
            (
                second_table.online_regular_price IS NOT NULL AND
                ((second_table.online_regular_sale_price_start_date IS NULL AND
                second_table.online_regular_sale_price_end_date IS NULL) OR
                second_table.online_regular_sale_price IS NULL OR
                second_table.online_regular_sale_price_start_date > CURRENT_DATE() OR
                second_table.online_regular_sale_price_end_date < CURRENT_DATE())
            )) OR (';

            if (isset($value['from']) && $value['from'] != '') {
                $condition .= 'second_table.online_regular_sale_price >= \''.(float)$value['from'].'\'';
            }
            if (isset($value['to']) && $value['to'] != '') {
                if (isset($value['from']) && $value['from'] != '') {
                    $condition .= ' AND ';
                }
                $condition .= 'second_table.online_regular_sale_price <= \''.(float)$value['to'].'\'';
            }

            $condition .= ' AND
            (
                second_table.online_regular_price IS NOT NULL AND
                (second_table.online_regular_sale_price_start_date IS NOT NULL AND
                second_table.online_regular_sale_price_end_date IS NOT NULL AND
                second_table.online_regular_sale_price IS NOT NULL AND
                second_table.online_regular_sale_price_start_date < CURRENT_DATE() AND
                second_table.online_regular_sale_price_end_date > CURRENT_DATE())
            )) OR (';

            if (isset($value['from']) && $value['from'] != '') {
                $condition .= 'online_business_price >= \''.(float)$value['from'].'\'';
            }
            if (isset($value['to']) && $value['to'] != '') {
                if (isset($value['from']) && $value['from'] != '') {
                    $condition .= ' AND ';
                }
                $condition .= 'second_table.online_business_price <= \''.(float)$value['to'].'\'';
            }

            $condition .= ' AND (second_table.online_regular_price IS NULL))';
        }

        if ($this->getHelper('Component_Amazon_Repricing')->isEnabled() &&
            (isset($value['is_repricing']) && $value['is_repricing'] !== '')) {
            if (!empty($condition)) {
                $condition = '(' . $condition . ') OR ';
            }

            $condition .= 'is_repricing = ' . (int)$value['is_repricing'];
        }

        $collection->getSelect()->where($condition);
    }

    protected function callbackFilterStatus($collection, $column)
    {
        $value = $column->getFilter()->getValue();

        if ($value == null) {
            return;
        }

        $collection->getSelect()->where('status = ?', $value);
    }

    protected function callbackFilterSku($collection, $column)
    {
        $value = $column->getFilter()->getValue();

        if ($value == null) {
            return;
        }

        $collection->getSelect()->where('`sku` LIKE ?', '%' . $value . '%');
    }

    //########################################

    public function getMainButtonsHtml()
    {
        $html = '';
        if ($this->getFilterVisibility()) {
            $html.= $this->getSearchButtonHtml();
            $html.= $this->getResetFilterButtonHtml();
            $html.= $this->getAddNewChildButtonsHtml();
        }
        return $html;
    }

    private function getAddNewChildButtonsHtml()
    {
        if ($this->isNewChildAllowed()) {
            // ---------------------------------------
            $data = [
                'label'   => $this->__('Add New Child Product'),
                'onclick' => 'ListingProductVariationManageVariationsGridObj.showNewChildForm(' .
                    var_export(!$this->hasUnusedChannelVariations(), true) .
                    ', ' . $this->getListingProduct()->getId() . ')',
                'class'   => 'action primary',
                'style'   => 'float: right;',
                'id'      => 'add_new_child_button'
            ];
            $buttonBlock = $this->createBlock('Magento\Button')->setData($data);
            $this->setChild('add_new_child_button', $buttonBlock);
            // ---------------------------------------
        }

        return $this->getChildHtml('add_new_child_button');
    }

    protected function isNewChildAllowed()
    {
        /** @var \Ess\M2ePro\Model\Amazon\Listing\Product $amazonListingProduct */
        $amazonListingProduct = $this->getListingProduct()->getChildObject();

        if (!$amazonListingProduct->getGeneralId()) {
            return false;
        }

        if (!$amazonListingProduct->getVariationManager()->getTypeModel()->hasMatchedAttributes()) {
            return false;
        }

        if (!$this->hasUnusedProductVariation()) {
            return false;
        }

        if ($this->hasChildWithEmptyProductOptions()) {
            return false;
        }

        if (!$this->isGeneralIdOwner() && !$this->hasUnusedChannelVariations()) {
            return false;
        }

        if (!$this->isGeneralIdOwner() && $this->hasChildWithEmptyChannelOptions()) {
            return false;
        }

        return true;
    }

    public function isGeneralIdOwner()
    {
        return $this->getListingProduct()->getChildObject()->isGeneralIdOwner();
    }

    public function getCurrentChannelVariations()
    {
        return $this->getListingProduct()->getChildObject()
            ->getVariationManager()->getTypeModel()->getChannelVariations();
    }

    public function hasUnusedProductVariation()
    {
        return (bool)$this->getListingProduct()
            ->getChildObject()
            ->getVariationManager()
            ->getTypeModel()
            ->getUnusedProductOptions();
    }

    public function hasUnusedChannelVariations()
    {
        return (bool)$this->getListingProduct()
            ->getChildObject()
            ->getVariationManager()
            ->getTypeModel()
            ->getUnusedChannelOptions();
    }

    public function hasChildWithEmptyProductOptions()
    {
        foreach ($this->getChildListingProducts() as $childListingProduct) {
            /** @var \Ess\M2ePro\Model\Listing\Product $childListingProduct */

            /** @var ChildRelation $childTypeModel */
            $childTypeModel = $childListingProduct->getChildObject()->getVariationManager()->getTypeModel();

            if (!$childTypeModel->isVariationProductMatched()) {
                return true;
            }
        }

        return false;
    }

    public function hasChildWithEmptyChannelOptions()
    {
        foreach ($this->getChildListingProducts() as $childListingProduct) {
            /** @var \Ess\M2ePro\Model\Listing\Product $childListingProduct */

            /** @var ChildRelation $childTypeModel */
            $childTypeModel = $childListingProduct->getChildObject()->getVariationManager()->getTypeModel();

            if (!$childTypeModel->isVariationChannelMatched()) {
                return true;
            }
        }

        return false;
    }

    public function getUsedChannelVariations()
    {
        return (bool)$this->getListingProduct()
            ->getChildObject()
            ->getVariationManager()
            ->getTypeModel()
            ->getUsedChannelOptions();
    }

    // ---------------------------------------

    public function getGridUrl()
    {
        return $this->getUrl('*/amazon_listing_product_variation_manage/viewVariationsGridAjax', [
            'product_id' => $this->getListingProduct()->getId()
        ]);
    }

    public function getRowUrl($row)
    {
        return false;
    }

    //########################################

    public function getTooltipHtml($content, $id = '', $classes = [])
    {
        $classes = implode(' ', $classes);

        return <<<HTML
    <div id="{$id}" class="m2epro-field-tooltip admin__field-tooltip {$classes}">
        <a class="admin__field-tooltip-action" href="javascript://"></a>
        <div class="admin__field-tooltip-content" style="">
            {$content}
        </div>
    </div>
HTML;
    }

    //########################################

    protected function _toHtml()
    {
        $this->css->add(
            <<<CSS
div.admin__filter-actions { width: 100%; }
CSS
        );

        $this->js->add(
            <<<JS
    require([
        'M2ePro/Amazon/Listing/Product/Variation/Manage/Tabs/Variations/Grid'
    ], function(){

        ListingProductVariationManageVariationsGridObj.afterInitPage();
        ListingProductVariationManageVariationsGridObj.actionHandler.messageObj.clear();

    });
JS
        );

        if ($this->getParam($this->getVarNameFilter()) == 'searched_by_child'){
            $noticeMessage = $this->__('This list includes a Product you are searching for.');
            $this->js->add(
                <<<JS
    require([
        'M2ePro/Amazon/Listing/Product/Variation/Manage/Tabs/Variations/Grid'
    ], function(){
        ListingProductVariationManageVariationsGridObj.actionHandler.messageObj.addNotice('{$noticeMessage}');
    });
JS
            );
        }

        return parent::_toHtml();
    }

    //########################################

    private function canChangeProductVariation(\Ess\M2ePro\Model\Listing\Product $childListingProduct)
    {
        if (!$this->hasUnusedProductVariation()) {
            return false;
        }

        $lockData = $this->getLockedData($childListingProduct);
        if ($lockData['in_action']) {
            return false;
        }

        /** @var \Ess\M2ePro\Model\Amazon\Listing\Product $amazonChildListingProduct */
        $amazonChildListingProduct = $childListingProduct->getChildObject();

        if (!$amazonChildListingProduct->getGeneralId()) {
            return false;
        }

        /** @var \Ess\M2ePro\Model\Amazon\Listing\Product\Variation\Manager\Type\Relation\ChildRelation $typeModel */
        $typeModel = $amazonChildListingProduct->getVariationManager()->getTypeModel();

        if ($typeModel->isVariationProductMatched() && $this->hasChildWithEmptyProductOptions()) {
            return false;
        }

        if (!$typeModel->getParentTypeModel()->hasMatchedAttributes()) {
            return false;
        }

        return true;
    }

    private function getLockedData($row)
    {
        $listingProductId = $row->getData('id');
        if (!isset($this->lockedDataCache[$listingProductId])) {
            $objectLocks = $this->activeRecordFactory->getObjectLoaded('Listing\Product', $listingProductId)
                ->getProcessingLocks();
            $tempArray = [
                'object_locks' => $objectLocks,
                'in_action' => !empty($objectLocks),
            ];
            $this->lockedDataCache[$listingProductId] = $tempArray;
        }

        return $this->lockedDataCache[$listingProductId];
    }

    //########################################

    protected function getTemplateDescriptionLinkHtml($listingProduct)
    {
        $templateDescriptionEditUrl = $this->getUrl('*/amazon_template_description/edit', [
            'id' => $listingProduct->getChildObject()->getTemplateDescriptionId()
        ]);

        $helper = $this->getHelper('Data');
        $templateTitle = $listingProduct->getChildObject()->getDescriptionTemplate()->getTitle();

        return <<<HTML
<span style="font-size: 9px;">{$helper->__('Description Title')}:&nbsp;
    <a target="_blank" href="{$templateDescriptionEditUrl}">
        {$helper->escapeHtml($templateTitle)}</a>
</span>
<br/>
HTML;
    }

    //########################################

    public function getProductVariationsTree($childProduct, $attributes)
    {
        $unusedVariations = $this->getUnusedProductVariations();

        /** @var ChildRelation $childTypeModel */
        $childTypeModel = $childProduct->getChildObject()->getVariationManager()->getTypeModel();

        if ($childTypeModel->isVariationProductMatched()) {
            $unusedVariations[] = $childTypeModel->getProductOptions();
        }

        $variationsSets = $this->getAttributesVariationsSets($unusedVariations);
        $variationsSetsSorted =  [];

        foreach ($attributes as $attribute) {
            $variationsSetsSorted[$attribute] = $variationsSets[$attribute];
        }

        $firstAttribute = key($variationsSetsSorted);

        return $this->prepareVariations($firstAttribute, $unusedVariations, $variationsSetsSorted);
    }

    private function prepareVariations($currentAttribute, $unusedVariations, $variationsSets, $filters = [])
    {
        $return = [];

        $temp = array_flip(array_keys($variationsSets));

        $lastAttributePosition = count($variationsSets) - 1;
        $currentAttributePosition = $temp[$currentAttribute];

        if ($currentAttributePosition != $lastAttributePosition) {
            $temp = array_keys($variationsSets);
            $nextAttribute = $temp[$currentAttributePosition + 1];

            foreach ($variationsSets[$currentAttribute] as $option) {
                $filters[$currentAttribute] = $option;

                $result = $this->prepareVariations(
                    $nextAttribute,
                    $unusedVariations,
                    $variationsSets,
                    $filters
                );

                if (!$result) {
                    continue;
                }

                $return[$currentAttribute][$option] = $result;
            }

            if (!empty($return)) {
                ksort($return[$currentAttribute]);
            }

            return $return;
        }

        $return = [];
        foreach ($unusedVariations as $key => $magentoVariation) {
            foreach ($magentoVariation as $attribute => $option) {
                if ($attribute == $currentAttribute) {
                    if (count($variationsSets) != 1) {
                        continue;
                    }

                    $values = array_flip($variationsSets[$currentAttribute]);
                    $return = [$currentAttribute => $values];

                    foreach ($return[$currentAttribute] as &$option) {
                        $option = true;
                    }

                    return $return;
                }

                if ($option != $filters[$attribute]) {
                    unset($unusedVariations[$key]);
                    continue;
                }

                foreach ($magentoVariation as $tempAttribute => $tempOption) {
                    if ($tempAttribute == $currentAttribute) {
                        $option = $tempOption;
                        $return[$currentAttribute][$option] = true;
                    }
                }
            }
        }

        if (empty($unusedVariations)) {
            return [];
        }

        if (!empty($return)) {
            ksort($return[$currentAttribute]);
        }

        return $return;
    }

    //########################################

    public function getCurrentProductVariations()
    {

        if ($this->currentProductVariations !== null) {
            return $this->currentProductVariations;
        }

        $magentoProductVariations = $this->getListingProduct()
            ->getMagentoProduct()
            ->getVariationInstance()
            ->getVariationsTypeStandard();

        $productVariations = [];

        foreach ($magentoProductVariations['variations'] as $option) {
            $productOption = [];

            foreach ($option as $attribute) {
                $productOption[$attribute['attribute']] = $attribute['option'];
            }

            $productVariations[] = $productOption;
        }

        return $this->currentProductVariations = $productVariations;
    }

    public function getUsedProductVariations()
    {
        if ($this->usedProductVariations === null) {
            $this->usedProductVariations = $this->getListingProduct()
                ->getChildObject()
                ->getVariationManager()
                ->getTypeModel()
                ->getUsedProductOptions();
        }

        return $this->usedProductVariations;
    }

    //########################################

    public function getUnusedProductVariations()
    {
        return $this->getListingProduct()
            ->getChildObject()
            ->getVariationManager()
            ->getTypeModel()
            ->getUnusedProductOptions();
    }

    private function isVariationExistsInArray(array $needle, array $haystack)
    {
        foreach ($haystack as $option) {
            if ($option != $needle) {
                continue;
            }

            return true;
        }

        return false;
    }

    //########################################

    public function getChildListingProducts()
    {
        if ($this->childListingProducts !== null) {
            return $this->childListingProducts;
        }

        return $this->childListingProducts = $this->getListingProduct()->getChildObject()
            ->getVariationManager()->getTypeModel()->getChildListingsProducts();
    }

    public function getAttributesVariationsSets($variations)
    {
        $attributesOptions = [];

        foreach ($variations as $variation) {
            foreach ($variation as $attr => $option) {
                if (!isset($attributesOptions[$attr])) {
                    $attributesOptions[$attr] = [];
                }
                if (!in_array($option, $attributesOptions[$attr], true)) {
                    $attributesOptions[$attr][] = $option;
                }
            }
        }

        return $attributesOptions;
    }

    //########################################

    protected function getGeneralIdLink($generalId)
    {
        $url = $this->getHelper('Component\Amazon')->getItemUrl(
            $generalId,
            $this->getListingProduct()->getListing()->getMarketplaceId()
        );

        return <<<HTML
<a href="{$url}" target="_blank" title="{$generalId}" >{$generalId}</a>
HTML;
    }

    //########################################

    private function parseGroupedData($data)
    {
        $result = [];

        if (empty($data)) {
            return $result;
        }

        $variationData = explode('||', $data);
        foreach ($variationData as $variationAttribute) {
            $value = explode('==', $variationAttribute);
            $result[$value[0]] = $value[1];
        }

        return $result;
    }

    //########################################

    private function convertAndFormatPriceCurrency($price, $currency)
    {
        return $this->localeCurrency->getCurrency($currency)->toCurrency($price);
    }

    //########################################
}
