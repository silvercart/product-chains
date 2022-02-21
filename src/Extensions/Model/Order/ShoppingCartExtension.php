<?php

namespace SilverCart\ProductChains\Extensions\Model\Order;

use SilverCart\Model\Pages\CartPage;
use SilverCart\Model\Product\Product;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataExtension;

/**
 * Extension for SilverCart ShoppingCart.
 * 
 * @package SilverCart
 * @subpackage ProductChains\Extensions\Model\Order
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 09.12.2020
 * @copyright 2020 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property \SilverCart\Model\Order\ShoppingCart $owner Owner
 */
class ShoppingCartExtension extends DataExtension
{
    /**
     * Overwrites the original product removal if necessary.
     * 
     * @param bool  &$overwriteRemoveProduct Set to true to overwrite the original method
     * @param array &$data                   Product data
     * 
     * @return void
     */
    public function overwriteRemoveProduct(bool &$overwriteRemoveProduct, array &$data) : void
    {
        $request = Controller::curr()->getRequest();
        $backURL = $request->postVar('BackURL');
        $page    = SiteTree::get_by_link($backURL);
        if (!($page instanceof CartPage)) {
            return;
        }
        $cartID  = $this->owner->ID;
        $product = Product::get()->byID($data['productID']);
        if ($product === null
         || $product->isGoingThroughTheChain()
        ) {
            return;
        }
        $total    = $product->getChainedShoppingCartQuantity($cartID);
        $position = $product->getShoppingCartPosition($cartID);
        if ($total > $position->Quantity) {
            $difference = $total - $position->Quantity;
            $product->setShoppingCartQuantity($cartID, $difference);
            $overwriteRemoveProduct = true;
        }
    }
}