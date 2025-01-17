<?php

/*
 * @author     M2E Pro Developers Team
 * @copyright  M2E LTD
 * @license    Commercial use is forbidden
 */

namespace Ess\M2ePro\Helper\Component;

use \Ess\M2ePro\Model\Listing\Product as ListingProduct;

class Walmart extends \Ess\M2ePro\Helper\AbstractHelper
{
    const NICK  = 'walmart';

    const MARKETPLACE_SYNCHRONIZATION_LOCK_ITEM_NICK = 'walmart_marketplace_synchronization';

    const MARKETPLACE_US = 37;
    const MARKETPLACE_CA = 38;

    const MAX_ALLOWED_FEED_REQUESTS_PER_HOUR = 30;

    const SKU_MAX_LENGTH = 50;

    const PRODUCT_PUBLISH_STATUS_PUBLISHED        = 'PUBLISHED';
    const PRODUCT_PUBLISH_STATUS_UNPUBLISHED      = 'UNPUBLISHED';
    const PRODUCT_PUBLISH_STATUS_STAGE            = 'STAGE';
    const PRODUCT_PUBLISH_STATUS_IN_PROGRESS      = 'IN_PROGRESS';
    const PRODUCT_PUBLISH_STATUS_READY_TO_PUBLISH = 'READY_TO_PUBLISH';
    const PRODUCT_PUBLISH_STATUS_SYSTEM_PROBLEM   = 'SYSTEM_PROBLEM';

    const PRODUCT_LIFECYCLE_STATUS_ACTIVE   = 'ACTIVE';
    const PRODUCT_LIFECYCLE_STATUS_RETIRED  = 'RETIRED';
    const PRODUCT_LIFECYCLE_STATUS_ARCHIVED = 'ARCHIVED';

    const PRODUCT_STATUS_CHANGE_REASON_INVALID_PRICE = 'Reasonable Price Not Satisfied';

    /** @var \Ess\M2ePro\Model\ActiveRecord\Component\Parent\Walmart\Factory */
    private $walmartFactory;

    /** @var \Ess\M2ePro\Helper\Module */
    protected $helperModule;

    /** @var \Ess\M2ePro\Helper\Module\Translation */
    protected $moduleTranslation;

    /** @var \Ess\M2ePro\Helper\Data\Cache\Permanent */
    protected $permanentCache;

    public function __construct(
        \Ess\M2ePro\Model\ActiveRecord\Component\Parent\Walmart\Factory $walmartFactory,
        \Ess\M2ePro\Helper\Module $helperModule,
        \Ess\M2ePro\Helper\Module\Translation $moduleTranslation,
        \Ess\M2ePro\Helper\Data\Cache\Permanent $permanentCache,
        \Ess\M2ePro\Helper\Factory $helperFactory,
        \Magento\Framework\App\Helper\Context $context
    ) {
        parent::__construct($helperFactory, $context);

        $this->walmartFactory = $walmartFactory;
        $this->helperModule = $helperModule;
        $this->moduleTranslation = $moduleTranslation;
        $this->permanentCache = $permanentCache;
    }

    //########################################

    public function getTitle()
    {
        return $this->helperFactory->getObject('Module\Translation')->__('Walmart');
    }

    public function getChannelTitle()
    {
        return $this->helperFactory->getObject('Module\Translation')->__('Walmart');
    }

    //########################################

    public function getHumanTitleByListingProductStatus($status)
    {
        $statuses = [
            ListingProduct::STATUS_UNKNOWN    => $this->moduleTranslation->__('Unknown'),
            ListingProduct::STATUS_NOT_LISTED => $this->moduleTranslation->__('Not Listed'),
            ListingProduct::STATUS_LISTED     => $this->moduleTranslation->__('Active'),
            ListingProduct::STATUS_STOPPED    => $this->moduleTranslation->__('Inactive'),
            ListingProduct::STATUS_BLOCKED    => $this->moduleTranslation->__('Inactive (Blocked)')
        ];

        if (!isset($statuses[$status])) {
            return null;
        }

        return $statuses[$status];
    }

    //########################################

    public function isEnabled()
    {
        return (bool)$this->helperModule->getConfig()->getGroupValue('/component/'.self::NICK.'/', 'mode');
    }

    //########################################

    public function getRegisterUrl($marketplaceId = self::MARKETPLACE_US)
    {

        $domain = $this->walmartFactory
            ->getCachedObjectLoaded('Marketplace', $marketplaceId)
            ->getUrl();

        if ($marketplaceId == self::MARKETPLACE_CA) {
            return 'https://seller.' . $domain . '/#/generateKey';
        }

        return 'https://developer.' . $domain . '/#/generateKey';
    }

    public function getIdentifierForItemUrl($marketplaceId)
    {
        switch ($marketplaceId) {
            case \Ess\M2ePro\Helper\Component\Walmart::MARKETPLACE_US:
                return 'item_id';
            case \Ess\M2ePro\Helper\Component\Walmart::MARKETPLACE_CA:
                return 'wpid';
            default:
                throw new \Ess\M2ePro\Model\Exception\Logic('Unknown Marketplace ID.');
        }
    }

    public function getItemUrl($productItemId, $marketplaceId = null)
    {
        $marketplaceId = (int)$marketplaceId;
        $marketplaceId <= 0 && $marketplaceId = self::MARKETPLACE_US;

        $domain = $this->walmartFactory
            ->getCachedObjectLoaded('Marketplace', $marketplaceId)
            ->getUrl();

        return 'https://www.'.$domain.'/ip/'.$productItemId;
    }

    //todo is not correct. there are no orders to check
    public function getOrderUrl($orderId, $marketplaceId = null)
    {
        $marketplaceId = (int)$marketplaceId;
        $marketplaceId <= 0 && $marketplaceId = self::MARKETPLACE_US;

        $domain = $this->walmartFactory
            ->getCachedObjectLoaded('Marketplace', $marketplaceId)
            ->getUrl();

        return 'https://seller.'.$domain.'/order-management/details./'.$orderId;
    }

    //########################################

    public function getApplicationName()
    {
        return (bool)$this->helperModule->getConfig()->getGroupValue('/walmart/', 'application_name');
    }

    // ----------------------------------------

    public function getCarriers()
    {
        return [
            'ups'      => 'UPS',
            'usps'     => 'USPS',
            'fedex'    => 'FedEx',
            'airborne' => 'Airborne',
            'ontrac'   => 'OnTrac',
            'dhl'      => 'DHL',
            'ng'       => 'NG',
            'ls'       => 'LS',
            'uds'      => 'UDS',
            'upsmi'    => 'UPSMI',
            'fdx'      => 'FDX'
        ];
    }

    public function getCarrierTitle($carrierCode, $title)
    {
        $carriers = $this->getCarriers();
        $carrierCode = strtolower($carrierCode);

        if (isset($carriers[$carrierCode])) {
            return $carriers[$carrierCode];
        }

        return $title;
    }

    // ----------------------------------------

    public function getMarketplacesAvailableForApiCreation()
    {
        return $this->walmartFactory->getObject('Marketplace')->getCollection()
                    ->addFieldToFilter('status', \Ess\M2ePro\Model\Marketplace::STATUS_ENABLE)
                    ->setOrder('sorder', 'ASC');
    }

    //########################################

    public function getResultProductStatus($publishStatus, $lifecycleStatus, $onlineQty)
    {
        if (!in_array($publishStatus, [self::PRODUCT_PUBLISH_STATUS_PUBLISHED,
                                            self::PRODUCT_PUBLISH_STATUS_STAGE]) ||
            $lifecycleStatus != self::PRODUCT_LIFECYCLE_STATUS_ACTIVE
        ) {
            return ListingProduct::STATUS_BLOCKED;
        }

        return $onlineQty > 0
            ? ListingProduct::STATUS_LISTED
            : ListingProduct::STATUS_STOPPED;
    }

    //########################################

    public function clearCache()
    {
        $this->permanentCache->removeTagValues(self::NICK);
    }

    //########################################
}
