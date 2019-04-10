<?php

namespace SilverCart\ProductChains\Extensions\Model\Product;

use SilverCart\Dev\Tools;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\FieldType\DBVarchar;

/**
 * Extension for SilverCart product translations.
 * 
 * @package SilverCart
 * @subpackage ProductChains\Extensions\Model\Product
 * @author Sebastian Diel <sdiel@pixeltricks.de>
 * @since 08.04.2019
 * @copyright 2019 pixeltricks GmbH
 * @license see license file in modules root directory
 */
class ProductTranslationExtension extends DataExtension
{
    /**
     * Has one relations.
     *
     * @var array
     */
    private static $db = [
        'ChainedProductPriceLabel' => DBVarchar::class,
    ];
    
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
}