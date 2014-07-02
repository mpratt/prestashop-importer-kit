
<?php
/**
 * Reseter.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Library autoloader, using a class map.
 * Saddly I need to support PHP 5.2, so I cant use PSR4
 * type autoloader.
 */
class Pik_Resetter
{
    /**
     * Clears the Cache
     *
     * @return void
     */
    public function cache($reindex = false)
    {
        Tools::enableCache();
        Tools::clearCache(Context::getContext()->smarty);
        Tools::restoreCacheSettings();

        if ($reindex) {
            Search::indexation(true);
        }
    }

    public function images()
    {
        if (!defined('_PS_CAT_IMG_DIR_') || !defined('_PS_PROD_IMG_DIR_')) {
            return ;
        }

        Image::deleteAllImages(_PS_PROD_IMG_DIR_);
        foreach (scandir(_PS_CAT_IMG_DIR_) as $d) {
            if (preg_match('/^[0-9]+(\-(.*))?\.jpg$/', $d)) {
                @unlink(_PS_CAT_IMG_DIR_.$d);
            }
        }
    }

    public function db($db)
    {
        if (!defined('_DB_PREFIX_')) {
            return ;
        }

        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_shop`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'feature_product`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_lang`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'category_product`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_tag`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'image`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'image_lang`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'image_shop`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'specific_price`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'specific_price_priority`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_carrier`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'cart_product`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'compare_product`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attachment`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_country_tax`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_download`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_group_reduction_cache`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_sale`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_supplier`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'scene_products`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'warehouse_product_location`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'stock`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'stock_available`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'stock_mvt`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'customization`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'customization_field`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'supply_order_detail`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_impact`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute_shop`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute_combination`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute_image`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'pack`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_impact`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_lang`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_group`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_group_lang`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_group_shop`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'attribute_shop`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute_shop`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute_combination`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'product_attribute_image`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer_lang`');
        $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'manufacturer_shop`');

        //check if table exist
        if (count($db->executeS('SHOW TABLES LIKE \''._DB_PREFIX_.'favorite_product\' '))) {
            $db->execute('TRUNCATE TABLE `'._DB_PREFIX_.'favorite_product`');
        }

        $db->execute(' DELETE FROM `'._DB_PREFIX_.'category`
            WHERE id_category NOT IN ('.(int)Configuration::get('PS_HOME_CATEGORY').
            ', '.(int)Configuration::get('PS_ROOT_CATEGORY').')');
        $db->execute('
            DELETE FROM `'._DB_PREFIX_.'category_lang`
            WHERE id_category NOT IN ('.(int)Configuration::get('PS_HOME_CATEGORY').
            ', '.(int)Configuration::get('PS_ROOT_CATEGORY').')');
        $db->execute('
            DELETE FROM `'._DB_PREFIX_.'category_shop`
            WHERE `id_category` NOT IN ('.(int)Configuration::get('PS_HOME_CATEGORY').
            ', '.(int)Configuration::get('PS_ROOT_CATEGORY').')');

        $db->execute('ALTER TABLE `'._DB_PREFIX_.'category` AUTO_INCREMENT = 3');
        $db->execute('DELETE FROM `'._DB_PREFIX_.'stock_available` WHERE id_product_attribute != 0');
    }

}
