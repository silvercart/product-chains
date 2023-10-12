<?php

namespace SilverCart\ProductChains\Extensions\Model\Order;

use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\Services\Service;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for SilverCart ShoppingCartPosition.
 * 
 * @package SilverCart
 * @subpackage ProductChains\Extensions\Model\Order
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 14.01.2020
 * @copyright 2020 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property ShoppingCartPosition $owner Owner
 */
class ShoppingCartPositionExtension extends DataExtension
{
    /**
     * Returns the chained total quantity.
     * 
     * @return float
     */
    public function getChainedTotalQuantity() : float
    {
        $product = $this->owner->Product();
        if (class_exists(Service::class)
         && $this->owner->exists()
         && $this->owner->ServiceParentPosition()->exists()
        ) {
            $product->setServiceParentPosition($this->owner->ServiceParentPosition());
        }
        return $product->getChainedShoppingCartQuantity($this->owner->ShoppingCartID);
    }

    /**
     * Updates whether this positions quantity is incrementable by the given
     * $quantity.
     * 
     * @param bool  &$isQuantityIncrementableBy Property to update
     * @param float $quantity                   Quantity
     * 
     * @return void
     */
    public function updateIsQuantityIncrementableBy(bool &$isQuantityIncrementableBy, float $quantity) : void
    {
        if (!class_exists(Service::class)) {
            return;
        }
        if ($this->owner->exists()
         && $this->owner->ServiceParentPosition()->exists()
        ) {
            $product = $this->owner->Product();
            $product->setServiceParentPosition($this->owner->ServiceParentPosition());
            if ((!$product->IsRequiredForEachServiceProduct
              || $this->owner->ServiceParentPosition()->Quantity === $this->owner->getChainedTotalQuantity())
            ) {
                $isQuantityIncrementableBy = false;
            }
        }
    }
}