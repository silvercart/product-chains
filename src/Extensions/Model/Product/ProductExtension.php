<?php

namespace SilverCart\ProductChains\Extensions\Model\Product;

use Moo\HasOneSelector\Form\Field as HasOneSelector;
use SilverCart\Dev\Tools;
use SilverCart\Model\Order\ShoppingCart;
use SilverCart\Model\Order\ShoppingCartPosition;
use SilverCart\Model\Pages\CartPage;
use SilverCart\Model\Product\Product;
use SilverCart\Model\Product\ProductTranslation;
use SilverCart\ProductWizard\Model\Wizard\StepOption;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBInt;
use SilverStripe\ORM\FieldType\DBMoney;
use SilverStripe\Security\Member;
use SilverStripe\Security\Security;

/**
 * Extension for SilverCart product.
 * 
 * @package SilverCart
 * @subpackage ProductChains\Extensions\Model\Product
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 08.04.2019
 * @copyright 2019 pixeltricks GmbH
 * @license see license file in modules root directory
 */
class ProductExtension extends DataExtension
{
    /**
     * Stores whether the current add-to-cart routine is already going through the
     * chain.
     *
     * @var bool
     */
    protected $isGoingThroughTheChain = false;
    /**
     * Stores whether the updateAfterPriceNiceContent method is called.
     *
     * @var bool
     */
    protected $isUpdateAfterPriceNiceContent = false;
    /**
     * Stores whether the updateBeforePriceNiceContent method is called.
     *
     * @var bool
     */
    protected $isUpdateBeforePriceNiceContent = false;
    /**
     * Has one relations.
     *
     * @var array
     */
    private static $db = [
        'MaximumCartQuantity'          => DBInt::class,
        'ShowChainedProductPriceLabel' => DBBoolean::class,
    ];
    /**
     * Has one relations.
     *
     * @var array
     */
    private static $has_one = [
        'ChainedProduct' => Product::class,
    ];
    /**
     * Belongs to relations (has one backside).
     *
     * @var array
     */
    private static $belongs_to = [
        'ChainedParentProduct' => Product::class . '.ChainedProduct',
    ];
    /**
     * Casting.
     *
     * @var array
     */
    private static $casting = [
        'ChainedProductPriceLabel' => 'Text',
        'ProductNumberAndTitle'    => 'Text',
    ];
    
    /**
     * Resets self assigned cheined product relations.
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 20.08.2019
     */
    public function requireDefaultRecords() : void
    {
        if (get_class($this->owner) === Product::class) {
            $table = $this->owner->config()->table_name;
            DB::query("UPDATE {$table} SET {$table}.ChainedProductID = 0 WHERE {$table}.ChainedProductID = {$table}.ID");
        }
    }
    
    /**
     * Updates the CMS fields.
     * 
     * @param FieldList $fields Fields to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateCMSFields(FieldList $fields) : void
    {
        if (class_exists(HasOneSelector::class)) {
            $chainedProductField = HasOneSelector::create('ChainedProduct', $this->owner->fieldLabel('ChainedProduct'), $this->owner, Product::class)->setLeftTitle($this->owner->fieldLabel('ChainedProductDesc'));
            $chainedProductField->removeAddable();
        } else {
            $source              = ['' => ''] + Product::get()->exclude('ID', $this->owner->ID)->map('ID', 'ProductNumberAndTitle')->toArray();
            $chainedProductField = DropdownField::create('ChainedProductID', $this->owner->fieldLabel('ChainedProduct'), $source)->setDescription($this->owner->fieldLabel('ChainedProductDesc'));
        }
        $fields->removeByName('ChainedProductID');
        $toggle = ToggleCompositeField::create(
                'ChainedProductToggle',
                $this->owner->fieldLabel('ChainedProduct'),
                [
                    $fields->dataFieldByName('MaximumCartQuantity')->setDescription($this->owner->fieldLabel('MaximumCartQuantityDesc')),
                    $chainedProductField,
                    $fields->dataFieldByName('ChainedProductPriceLabel')->setDescription(ProductTranslation::singleton()->fieldLabel('ChainedProductPriceLabelDesc')),
                    $fields->dataFieldByName('ShowChainedProductPriceLabel'),
                ]
        )->setHeadingLevel(4)->setStartClosed(true);
        $fields->removeByName('MaximumCartQuantity');
        $fields->removeByName('ChainedProductID');
        $fields->removeByName('ChainedProductPriceLabel');
        $fields->removeByName('ShowChainedProductPriceLabel');
        
        $this->owner->extend('updateFieldsForChainedProduct', $toggle, $fields);
        $fields->insertAfter($toggle, 'TimeGroupToggle');
    }
    
    /**
     * Updates the field labels.
     * 
     * @param array &$labels Labels to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateFieldLabels(&$labels) : void
    {
        $labels = array_merge($labels, Tools::field_labels_for(self::class));
    }
    
    /**
     * Updates the content to render right after the nice price is rendered.
     * 
     * @param string &$content Content to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateAfterPriceNiceContent(string &$content) : void
    {
        if ($this->isUpdateAfterPriceNiceContent()
         || $this->isUpdateBeforePriceNiceContent()
        ) {
            return;
        }
        $this->setIsUpdateAfterPriceNiceContent(true);
        $chained = $this->owner->ChainedProduct();
        if ($chained->exists()) {
            $content .= $chained->renderWith(self::class . '_AfterPriceNice');
        }
        $this->setIsUpdateAfterPriceNiceContent(false);
    }
    
    /**
     * Updates the content to render right before the nice price is rendered.
     * 
     * @param string &$content Content to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateBeforePriceNiceContent(string &$content) : void
    {
        if (!$this->hasChainedProduct()
         && !$this->hasChainedParentProduct()
        ) {
            if ($this->owner->ShowChainedProductPriceLabel) {
                $content .= $this->owner->renderWith(self::class . '_BeforePriceNice');
            }
            return;
        }
        if ($this->isUpdateAfterPriceNiceContent()
         || $this->isUpdateBeforePriceNiceContent()
        ) {
            if ($this->isUpdateBeforePriceNiceContent()) {
                $content .= $this->owner->renderWith(self::class . '_BeforePriceNice');
            }
            return;
        }
        if (!$this->isUpdateBeforePriceNiceContent()
         && $this->hasChainedParentProduct()
        ) {
            $this->setIsUpdateBeforePriceNiceContent(true);
            $parts   = [];
            $product = $this->owner;
            while ($product->ChainedParentProduct()->exists()) {
                $product = $product->ChainedParentProduct();
                $parts[] = $product->renderWith(self::class . '_BeforePriceNiceParent');
            }
            $content .= implode('', array_reverse($parts));
        }
        $this->setIsUpdateBeforePriceNiceContent(true);
        $content .= $this->owner->renderWith(self::class . '_BeforePriceNice');
        $this->setIsUpdateBeforePriceNiceContent(false);
    }
    
    /**
     * Updates the content to render right after the context product is rendered
     * in a shopping cart ajax response.
     * 
     * @param string &$content Content to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateAfterShoppingCartAjaxResponseContent(string &$content) : void
    {
        $position = $this->getChainedProductShoppingCartPosition();
        while ($position instanceof ShoppingCartPosition
            && $position->exists()
        ) {
            $content .= "<span class=\"h2\">{$position->getTypeSafeQuantity()}x</span>";
            $content .= $position->Product()->renderWith('SilverCart\Model\Order\Includes\ShoppingCart_AjaxResponse_Position');
            $position = $position->Product()->getChainedProductShoppingCartPosition();
        }
    }
    
    /**
     * Updates the content to render right before the context product is rendered
     * in a shopping cart ajax response.
     * 
     * @param string &$content Content to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateBeforeShoppingCartAjaxResponseContent(string &$content) : void
    {
        $chainedParentPosition = $this->getChainedParentProductShoppingCartPosition();
        while ($chainedParentPosition instanceof ShoppingCartPosition
         && $chainedParentPosition->exists()
        ) {
            $content .= "<span class=\"h2\">{$chainedParentPosition->getTypeSafeQuantity()}x</span>";
            $content .= $chainedParentPosition->Product()->renderWith('SilverCart\Model\Order\Includes\ShoppingCart_AjaxResponse_Position');
            $chainedParentPosition = $chainedParentPosition->Product()->getChainedParentProductShoppingCartPosition();
        }
        $chainedParentPosition = $this->getChainedParentProductShoppingCartPosition();
        $chainedPosition       = $this->getChainedProductShoppingCartPosition();
        $position              = $this->getShoppingCartPosition();
        if ((($chainedParentPosition instanceof ShoppingCartPosition
           && $chainedParentPosition->exists())
          || ($chainedPosition instanceof ShoppingCartPosition
           && $chainedPosition->exists()))
         && $position->Quantity > 0
        ) {
            $content .= "<span class=\"h2\">{$position->getTypeSafeQuantity()}x</span>";
        }
    }
    
    /**
     * Updates the content to render instead of the context product's shopping 
     * cart ajax response default content.
     * 
     * @param string &$content Content to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.04.2019
     */
    public function updateOverwriteShoppingCartAjaxResponseContent(string &$content) : void
    {
        $chainedParentPosition = $this->getChainedParentProductShoppingCartPosition();
        $position              = $this->getShoppingCartPosition();
        if (($chainedParentPosition instanceof ShoppingCartPosition
          && $chainedParentPosition->exists())
         && $position->Quantity == 0
        ) {
            $content .= "<span></span>";
        }
    }
    
    /**
     * Updates the product wizard cart data after the option data was created.
     * 
     * @param array &$cartData  Cart data to update
     * @param array $optionData Option data
     * @param int   $quantity   Quantity
     * 
     * @return void
     */
    public function updateAfterProductWizardStepOptionAddCartData(array &$cartData, array $optionData, int $quantity) : void
    {
        $product = $this->owner;
        /* @var $product Product */
        if (!$product->hasChainedParentProduct()
         && (!$product->hasChainedProduct()
          || $product->MaximumCartQuantity <= 0)
        ) {
            $this->setIsGoingThroughTheChain(false);
            return;
        }
        if ($this->isGoingThroughTheChain()
         && (!$product->hasChainedProduct()
          || $product->MaximumCartQuantity <= 0)
        ) {
            $this->setIsGoingThroughTheChain(false);
            return;
        }
        if ($product->IsNotBuyable
         || $quantity == 0
         || !$product->isBuyableDueToStockManagementSettings()
        ) {
            return;
        }
        $this->setProductWizardCartSummaryQuantity($cartData, $optionData, $quantity);
    }
    
    /**
     * Returns a map of cart data indey and product ID.
     * 
     * @param array $cartData Cart data
     * 
     * @return array
     */
    protected function getProductWizardCartSummaryProductIDMap(array $cartData) : array
    {
        $productIDMap = [];
        foreach ($cartData as $key => $positionData) {
            $productIDMap[$key] = $positionData['productID'];
        }
        return $productIDMap;
    }
    
    /**
     * Sets the product wizard cart summary data for the given $cartData and $quantity.
     * 
     * @param array &$cartData  Cart data to update
     * @param array $optionData Option data
     * @param int   $quantity   Quantity
     * 
     * @return void
     */
    protected function setProductWizardCartSummaryQuantity(array &$cartData, array $optionData, int $quantity) : void
    {
        $product = $this->owner;
        /* @var $product Product */
        if (!$this->isGoingThroughTheChain()
         && $product->hasChainedParentProduct()
        ) {
            $this->setIsGoingThroughTheChain(true);
            while ($product->ChainedParentProduct()->exists()) {
                $product = $product->ChainedParentProduct();
            }
        }
        $productIDMap = $this->getProductWizardCartSummaryProductIDMap($cartData);
        if ($product->hasChainedProduct()
         && $quantity <= $product->MaximumCartQuantity
        ) {
            // delete data for chained products.
            do {
                $chainedProduct = $product->ChainedProduct();
                $productIDKey   = array_search($chainedProduct->ID, $productIDMap);
                if ($productIDKey !== false) {
                    unset($cartData[$productIDKey]);
                }
            } while ($chainedProduct->ChainedProduct()->exists());
        }
        $this->setProductWizardCartSummaryQuantityFor($cartData, $product, $quantity);
        $this->setIsGoingThroughTheChain(false);
    }
    
    /**
     * Sets the product wizard cart summary data for the given $product and $quantity.
     * 
     * @param array   &$cartData Cart data
     * @param Product $product   Product
     * @param int     $quantity  Quantity
     * 
     * @return void
     */
    protected function setProductWizardCartSummaryQuantityFor(array &$cartData, Product $product, int $quantity) : void
    {
        if (!$product->exists()) {
            return;
        }
        $productIDMap = $this->getProductWizardCartSummaryProductIDMap($cartData);
        $productIDKey = array_search($product->ID, $productIDMap);
        if ($productIDKey === false) {
            if (empty($cartData)) {
                $productIDKey = 1;
            } else {
                $productIDKey = max(array_keys($cartData)) + 1;
            }
            $positionData            = StepOption::getCartPositionData($quantity, $product);
            $cartData[$productIDKey] = $positionData;
        } else {
            $positionData = $cartData[$productIDKey];
        }
        if ($product->MaximumCartQuantity > 0
         && $quantity > $product->MaximumCartQuantity
        ) {
            $priceSingle   = $positionData['priceSingle'];
            $priceCurrency = $priceSingle['Currency'];
            $priceAmount   = $priceSingle['Amount'];
            $priceTotal    = DBMoney::create()->setCurrency($priceCurrency)->setAmount($priceAmount * $product->MaximumCartQuantity);
            $chainQuantity = $quantity - $product->MaximumCartQuantity;
            $cartData[$productIDKey]['productQuantity'] = $product->MaximumCartQuantity;
            $cartData[$productIDKey]['priceTotal']      = [
                'Amount'   => $priceTotal->getAmount(),
                'Currency' => $priceTotal->getCurrency(),
                'Nice'     => $priceTotal->Nice(),
            ];
            $this->setProductWizardCartSummaryQuantityFor($cartData, $product->ChainedProduct(), $chainQuantity);
        }
    }
    
    /**
     * Updates the default addToCart method to add support for chained products.
     * 
     * @param int                  $cartID            Shopping cart ID
     * @param float                $quantity          Quantity
     * @param bool                 $increment         Incement mode?
     * @param bool                 &$addToCartAllowed Determines whether the original addToCart()
     *                                                method will be called or not. 
     * @param ShoppingCartPosition &$position         Affected shopping cart position
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateAddToCart(int $cartID, float $quantity, bool $increment, bool &$addToCartAllowed, ShoppingCartPosition &$position = null) : void
    {
        $product = $this->owner;
        /* @var $product Product */
        if (!$product->hasChainedParentProduct()
         && (!$product->hasChainedProduct()
          || $product->MaximumCartQuantity <= 0)
        ) {
            $this->setIsGoingThroughTheChain(false);
            return;
        }
        if ($this->isGoingThroughTheChain()
         && (!$product->hasChainedProduct()
          || $product->MaximumCartQuantity <= 0)
        ) {
            $this->setIsGoingThroughTheChain(false);
            return;
        }
        if ($product->IsNotBuyable
         || $quantity == 0
         || $cartID == 0
         || !$product->isBuyableDueToStockManagementSettings()
        ) {
            $addToCartAllowed = false;
            return;
        }
        $request = Controller::curr()->getRequest();
        $backURL = $request->postVar('BackURL');
        $page    = SiteTree::get_by_link($backURL);
        if ($page instanceof CartPage) {
            if ($position === null) {
                $position = $product->getShoppingCartPosition($cartID);
            }
            if ($position instanceof ShoppingCartPosition) {
                $difference = $quantity - $position->Quantity;
                if ($increment) {
                    $quantity = $difference;
                } else {
                    $total    = $this->getChainedShoppingCartQuantity($cartID);
                    $quantity = $total + $difference;
                }
            }
        }
        if ($increment) {
            $position = $this->incrementShoppingCartQuantity($cartID, $quantity, $addToCartAllowed);
        } else {
            $position = $this->setShoppingCartQuantity($cartID, $quantity, $addToCartAllowed);
        }
    }
    
    /**
     * Updates the cart removal of a product.
     * 
     * @param int $cartID Shopping cart ID
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.04.2019
     */
    public function updateRemoveFromCart(int $cartID) : void
    {
        if ($this->isGoingThroughTheChain()) {
            return;
        }
        $product = $this->owner;
        /* @var $product Product */
        $this->setIsGoingThroughTheChain(true);
        while ($product->hasChainedParentProduct()) {
            $product = $product->ChainedParentProduct();
            ShoppingCart::removeProduct(['productID' => $product->ID]);
        }
    }
    
    /**
     * Returns the total shopping cart quantity of the whole product chain.
     * 
     * @param int $cartID Cart ID
     * 
     * @return float
     */
    public function getChainedShoppingCartQuantity(int $cartID) : float
    {
        $quantity = 0;
        $product  = $this->owner;
        /* @var $product Product */
        $parent   = $product;
        do {
            $position  = $parent->getShoppingCartPosition($cartID);
            $quantity += $position->Quantity;
            $parent    = $parent->ChainedParentProduct();
        } while ($parent->exists());
        if ($product->hasChainedProduct()) {
            $chainedProduct = $product->ChainedProduct();
            while ($chainedProduct->exists()) {
                $position       = $chainedProduct->getShoppingCartPosition($cartID);
                $quantity      += $position->Quantity;
                $chainedProduct = $chainedProduct->ChainedProduct();
            }
        }
        return $quantity;
    }
    
    /**
     * Updates whether this product is in cart.
     * 
     * @param bool &$isInCart Product is in cart?
     * @param int  $cartID    Shopping cart ID
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateIsInCart(bool &$isInCart, int $cartID = null) : void
    {
        $product = $this->owner;
        /* @var $product Product */
        if ($product->getPositionInCart($cartID) instanceof ShoppingCartPosition) {
            $isInCart = true;
            return;
        }
        while ($product->ChainedParentProduct()->exists()) {
            if ($product->getPositionInCart($cartID) instanceof ShoppingCartPosition) {
                $isInCart = true;
                return;
            }
            $product = $product->ChainedParentProduct();
        }
        while ($product->ChainedProduct()->exists()) {
            if ($product->getPositionInCart($cartID) instanceof ShoppingCartPosition) {
                $isInCart = true;
                return;
            }
            $product = $product->ChainedProduct();
        }
    }
    
    /**
     * Updates the display cart quantity of this product.
     * 
     * @param float &$quantity Quantity to update
     * 
     * @return void
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function updateQuantityInCart(float &$quantity) : void
    {
        $chainedParentPosition = $this->getChainedParentProductShoppingCartPosition();
        if ($chainedParentPosition instanceof ShoppingCartPosition
         && $chainedParentPosition->exists()
        ) {
            $quantity += $chainedParentPosition->Quantity;
        }
        $chainedPosition = $this->getChainedProductShoppingCartPosition();
        if ($chainedPosition instanceof ShoppingCartPosition
         && $chainedPosition->exists()
        ) {
            $quantity += $chainedPosition->Quantity;
        }
    }
    
    /**
     * Returns the shopping cart position for this product and the given $cartID.
     * If the position doesn't exist yet, it will be created.
     * 
     * @param int $cartID Shopping cart ID
     * 
     * @return ShoppingCartPosition
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function getShoppingCartPosition(int $cartID = null) : ShoppingCartPosition
    {
        if (is_null($cartID)) {
            $user = Security::getCurrentUser();
            if ($user instanceof Member
             && $user->exists()
            ) {
                $cartID = $user->ShoppingCart()->ID;
            }
        }
        $product = $this->owner;
        /* @var $product Product */
        $isNewPosition = false;
        $position      = ShoppingCartPosition::get()->filter([
            'ProductID'      => $product->ID,
            'ShoppingCartID' => $cartID,
        ])->first();
        if (!($position instanceof ShoppingCartPosition)
         || !$position->exists()
        ) {
            $isNewPosition = true;
            $position      = ShoppingCartPosition::create()
                    ->castedUpdate([
                        'ShoppingCartID' => $cartID,
                        'ProductID'      => $product->ID,
                    ]);
            $position->write();
        }
        $position->IsNewPosition = $isNewPosition;
        return $position;
    }
    
    /**
     * Returns the shopping cart position for the chained parent product and the 
     * given $cartID.
     * 
     * @param int $cartID Shopping cart ID
     * 
     * @return ShoppingCartPosition
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function getChainedParentProductShoppingCartPosition(int $cartID = null) : ?ShoppingCartPosition
    {
        if (is_null($cartID)) {
            $user = Security::getCurrentUser();
            if ($user instanceof Member
             && $user->exists()
            ) {
                $cartID = $user->ShoppingCart()->ID;
            }
        }
        return ShoppingCartPosition::get()->filter([
            'ProductID'      => $this->owner->ChainedParentProduct()->ID,
            'ShoppingCartID' => $cartID,
        ])->first();
    }
    
    /**
     * Returns the shopping cart position for the chained product and the given 
     * $cartID.
     * 
     * @param int $cartID Shopping cart ID
     * 
     * @return ShoppingCartPosition
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function getChainedProductShoppingCartPosition(int $cartID = null) : ?ShoppingCartPosition
    {
        if (is_null($cartID)) {
            $user = Security::getCurrentUser();
            if ($user instanceof Member
             && $user->exists()
            ) {
                $cartID = $user->ShoppingCart()->ID;
            }
        }
        return ShoppingCartPosition::get()->filter([
            'ProductID'      => $this->owner->ChainedProduct()->ID,
            'ShoppingCartID' => $cartID,
        ])->first();
    }
    
    /**
     * Deletes the shopping cart position for the chaied product and the cart with
     * the given $cartID.
     * 
     * @param int $cartID Shopping cart ID
     * 
     * @return Product
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function deleteChainedProductShoppingCartPosition(int $cartID) : Product
    {
        $position = $this->getChainedProductShoppingCartPosition($cartID);
        if ($position instanceof ShoppingCartPosition
         && $position->exists()
        ) {
            $position->delete();
        }
        return $this->owner;
    }
    
    /**
     * Increments the shopping cart quantity respecting the chained product.
     * 
     * @param int   $cartID            Shopping cart ID
     * @param float $quantity          Quantity to increment by
     * @param bool  &$addToCartAllowed Determines whether the original addToCart()
     *                                 method will be called or not.
     * 
     * @return ShoppingCartPosition
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function incrementShoppingCartQuantity(int $cartID, float $quantity, bool &$addToCartAllowed) : ShoppingCartPosition
    {
        $product  = $this->owner;
        $position = $this->getShoppingCartPosition($cartID);
        /* @var $product Product */
        /* @var $position ShoppingCartPosition */
        if ($position->Quantity + $quantity <= $product->MaximumCartQuantity) {
            return $position;
        }
        $position->Quantity = $product->MaximumCartQuantity;
        $chainQuantity      = $quantity - $position->Quantity;
        $addToCartAllowed   = false;
        $position->write();
        $product->ChainedProduct()->addToCart($cartID, $chainQuantity);
        return $position;
    }
    
    /**
     * Sets the shopping cart quantity respecting the chained product.
     * 
     * @param int   $cartID            Shopping cart ID
     * @param float $quantity          Quantity to increment by
     * @param bool  &$addToCartAllowed Determines whether the original addToCart()
     *                                 method will be called or not.
     * 
     * @return ShoppingCartPosition
     */
    public function setShoppingCartQuantity(int $cartID, float $quantity, bool &$addToCartAllowed = null) : ?ShoppingCartPosition
    {
        $product = $this->owner;
        /* @var $product Product */
        if (!$this->isGoingThroughTheChain()) {
            $this->setIsGoingThroughTheChain(true);
            if ($product->hasChainedParentProduct()) {
                while ($product->ChainedParentProduct()->exists()) {
                    $product = $product->ChainedParentProduct();
                }
            }
        }
        if ($product->hasChainedProduct() 
         && $quantity <= $product->MaximumCartQuantity
        ) {
            $chainedProduct = $product;
            do {
                $chainedProduct->deleteChainedProductShoppingCartPosition($cartID);
                $chainedProduct = $chainedProduct->ChainedProduct();
            } while ($chainedProduct->ChainedProduct()->exists());
            $position = $product->getShoppingCartPosition($cartID);
            $position->Quantity = $quantity;
            $position->write();
            $addToCartAllowed = false;
            return null;
        }
        $position = $product->getShoppingCartPosition($cartID);
        /* @var $position ShoppingCartPosition */
        if ($product->MaximumCartQuantity > 0
         && $quantity > $product->MaximumCartQuantity
        ) {
            $position->Quantity = $product->MaximumCartQuantity;
            $chainQuantity      = $quantity - $position->Quantity;
            $position->write();
            $product->ChainedProduct()->addToCart($cartID, $chainQuantity);
            $addToCartAllowed = false;
        }
        return $position;
    }

    /**
     * Returns the ChainedProductPriceLabel property out of the current translation 
     * context.
     * 
     * @return string
     */
    public function getChainedProductPriceLabel() : ?string
    {
        return $this->owner->getTranslationFieldValue('ChainedProductPriceLabel');
    }
    
    /**
     * Returns the product number with title.
     * 
     * @return string
     */
    public function getProductNumberAndTitle() : string
    {
        $string = $this->owner->Title;
        if (!empty($this->owner->ProductNumberShop)) {
            $string = "{$this->owner->ProductNumberShop} | {$string}";
        }
        return (string) $string;
    }
    
    /**
     * Returns whether this product has a chained product.
     * 
     * @return bool
     */
    public function hasChainedProduct() : bool
    {
        return $this->owner->ChainedProduct()->exists();
    }
    
    /**
     * Returns whether this product has a chained parent product.
     * 
     * @return bool
     */
    public function hasChainedParentProduct() : bool
    {
        return $this->owner->ChainedParentProduct()->exists();
    }
    
    /**
     * Returns whether this product is part of a product chain.
     * 
     * @return bool
     */
    public function isInProductChain() : bool
    {
        return $this->hasChainedProduct()
            || $this->hasChainedParentProduct();
    }
    
    /**
     * Returns whether the current add-to-cart routine is going through the chain.
     * 
     * @return bool
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 08.04.2019
     */
    public function isGoingThroughTheChain() : bool
    {
        return $this->isGoingThroughTheChain;
    }
    
    /**
     * Sets whether the current add-to-cart routine is going through the chain.
     * 
     * @param bool $isGoingThroughTheChain Add-to-cart routine is going through the chain?
     * 
     * @return Product
     */
    public function setIsGoingThroughTheChain(bool $isGoingThroughTheChain) : Product
    {
        $this->isGoingThroughTheChain = $isGoingThroughTheChain;
        return $this->owner;
    }
    
    /**
     * Returns whether the updateAfterPriceNiceContent hook is currently called.
     * 
     * @return bool
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.04.2019
     */
    public function isUpdateAfterPriceNiceContent() : bool
    {
        return $this->isUpdateAfterPriceNiceContent;
    }
    
    /**
     * Sets whether the updateAfterPriceNiceContent hook is currently called.
     * 
     * @param bool $is Hook is called?
     * 
     * @return Product
     */
    public function setIsUpdateAfterPriceNiceContent(bool $is) : Product
    {
        $this->isUpdateAfterPriceNiceContent = $is;
        return $this->owner;
    }
    
    /**
     * Returns whether the updateBeforePriceNiceContent hook is currently called.
     * 
     * @return bool
     * 
     * @author Sebastian Diel <sdiel@pixeltricks.de>
     * @since 10.04.2019
     */
    public function isUpdateBeforePriceNiceContent() : bool
    {
        return $this->isUpdateBeforePriceNiceContent;
    }
    
    /**
     * Sets whether the updateBeforePriceNiceContent hook is currently called.
     * 
     * @param bool $is Hook is called?
     * 
     * @return Product
     */
    public function setIsUpdateBeforePriceNiceContent(bool $is) : Product
    {
        $this->isUpdateBeforePriceNiceContent = $is;
        return $this->owner;
    }
}