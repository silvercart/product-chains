<?php

namespace SilverCart\ProductChains\Extensions\Forms;

use SilverCart\Forms\CustomForm;
use SilverCart\Model\Customer\Customer;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Extension;

/**
 * Extension for SilverCart RemovePositionForm.
 * 
 * @package SilverCart
 * @subpackage ProductChains\Extensions\Forms
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 09.12.2020
 * @copyright 2020 pixeltricks GmbH
 * @license see license file in modules root directory
 * 
 * @property \SilverCart\Forms\RemovePositionForm $owner Owner
 */
class RemovePositionFormExtension extends Extension
{
    /**
     * Overwrites the original submission if necessary.
     * 
     * @param array      &$data        Position data
     * @param CustomForm $form         Form context
     * @param bool       &$overwritten Original method overwritten?
     * 
     * @return void
     */
    public function overwriteDoSubmit(array &$data, CustomForm $form, bool &$overwritten) : void
    {
        if (array_key_exists('PositionID', $data)
         && is_numeric($data['PositionID'])
        ) {
            //check if the position belongs to this user. Malicious people could manipulate it.
            $member   = Customer::currentUser();
            $position = ShoppingCartPosition::get()->byID($data['PositionID']);
            if ($position instanceof ShoppingCartPosition
             && $position->exists()
             && $position->ShoppingCartID == $member->getCart()->ID
            ) {
                $cartID  = $member->getCart()->ID;
                $product = $position->Product();
                if ($product->isGoingThroughTheChain()) {
                    return;
                }
                $total    = $product->getChainedShoppingCartQuantity($cartID);
                $position = $product->getShoppingCartPosition($cartID);
                if ($total > $position->Quantity) {
                    $difference = $total - $position->Quantity;
                    $product->setShoppingCartQuantity($cartID, $difference);
                    $overwritten = true;
                    $backLinkPage = SiteTree::get()->byID($data['BlID']);
                    $this->owner->getController()->redirect($backLinkPage->Link());
                }
            }
        }
    }
}