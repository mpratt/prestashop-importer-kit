<?php
/**
 * Product.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Class responsable of importing Products
 */
class Pik_Importer_Product extends Pik_Importer_Adapter implements Pik_Importer_Interface
{
    /** @var object Instance of Pik_Importer_Categories */
    protected $category = null;

    /** inline {@inheritdoc} */
    public function __construct(array $config = array(), array $filters = array())
    {
        $this->category = new Pik_Importer_Categories($config, $filters);
        parent::__construct($config, $filters);
    }

    /** inline {@inheritdoc} */
    public function import(array $data)
    {
        try {
            if (isset($data['id'])) {
                $product = $this->getProduct((int) $data['id']);
            } else {
                $product = $this->getProduct(null);
            }

            $product = $this->mergeData($product, $data);
            if (empty($product->name) || (isset($product->name[$this->config['id_lang']]) && strtolower($product->name[$this->config['id_lang']]) == 'name')) {
                throw new RuntimeException('The product ' . $data['id'] . ' needs a name');
            }

            if (empty($product->link_rewrite[$this->config['id_lang']])) {
                $product->link_rewrite[$this->config['id_lang']] = Tools::link_rewrite($product->name[$this->config['id_lang']]);
            }

            $this->calculatePrice($product);
            $this->normalizeManufacturer($product);
            $this->normalizeCategories($product);
            $this->addSpecialPrice($product);

            if (Product::existsInDatabase((int) $data['id'], 'product')) {
                $product->update();
            } else {
                $product->add();
            }

            StockAvailable::setQuantity($product->id, null, (int) $product->quantity);
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    /**
     * Disables a product on the shop
     *
     * @param mixed $id
     * @return
     */
    public function disableProduct($id)
    {
        Db::getInstance()->update('product', array(
            'active' => 0
        ), 'id_product = ' . pSQL((int) $id));

        return $this->updateDb(array(
            'active' => 0
        ), $id);
    }

    /**
     * Enables a product on the shop
     *
     * @param mixed $id
     * @return
     */
    public function enableProduct($id)
    {
        Db::getInstance()->update('product', array(
            'active' => 1
        ), 'id_product = ' . pSQL((int) $id));

        return $this->updateDb(array(
            'active' => 1
        ), $id);
    }


    /**
     * Disables a product on the shop
     *
     * @param mixed $id
     * @return
     */
    public function isActiveProduct($id)
    {
        $sql = 'SELECT active
            FROM '._DB_PREFIX_.'product_shop
            WHERE id_product = ' . pSQL((int) $id);

        return Db::getInstance()->getValue($sql);
    }

    /**
     * Updates product information directly on the database
     *
     * @param array $data
     * @param mixed $id
     * @param string $table
     * @return
     */
    public function updateDb(array $data, $id, $table = 'product_shop')
    {
        return Db::getInstance()->update($table, $data, 'id_product = ' . pSQL((int) $id));
    }

    /**
     * Normalizes/Creates the manufacturer found inside a product
     *
     * @param object $product
     * @return void
     */
    protected function normalizeManufacturer($product)
    {
        if (empty($product->manufacturer) || is_array($product->manufacturer) || preg_match('~^[0-9]*$~', $product->manufacturer)) {
            return ;
        } else if (Manufacturer::manufacturerExists((int) $product->manufacturer)) {
            $product->id_manufacturer = (int) $product->manufacturer;
        } else {
            if ($manufacturer = Manufacturer::getIdByName($product->manufacturer)) {
                $product->id_manufacturer = (int) $manufacturer;
            } else {
                $manufacturer = new Manufacturer();
                $manufacturer->name = $product->manufacturer;
                $manufacturer->active = 1;
                $manufacturer->add();
                $product->id_manufacturer = (int) $manufacturer->id;
                $manufacturer->associateTo($product->id_shop_list);
            }
        }
    }

    /**
     * Calculates the price of a product
     *
     * @param object $product
     * @return void
     */
    protected function calculatePrice($product)
    {
        if (!empty($product->price_tex)) {
            $product->price = $product->price_tex;
        } else if (!empty($product->price_tin)) {
            $product->price = $product->price_tin;

            // If a tax is already included in price, withdraw it from price
            if ($product->tax_rate) {
                $product->price = (float) number_format($product->price / (1 + $product->tax_rate / 100), 6, '.', '');
            }
        } else {
            $product->price = 0;
        }
    }

    /**
     * Normalizes/Creates the categories found inside a product
     *
     * @param object $product
     * @return void
     */
    protected function normalizeCategories($product)
    {
        $catIds = array();
        $categories = array_merge((array) $product->category, (array) $product->id_category);
        foreach ((array) $categories as $id) {
            $category = $this->category->createCategory($id, $id);
            if (empty($category)) {
                continue ;
            }

            $catIds[] = (int) $category->id;
            $product->addToCategories($category->id);
        }

        if (empty($product->id_category_default) || !Category::categoryExists((int) $product->id_category_default)) {
            $catIds = array_reverse(array_unique($catIds));
            $ignore = array(Configuration::get('PS_ROOT_CATEGORY'), Configuration::get('PS_HOME_CATEGORY'));
            foreach ($catIds as $defaultId) {
                if (!in_array($defaultId, $ignore) && Category::categoryExists((int) $defaultId)) {
                    $product->id_category_default = $defaultId;
                    break;
                }
            }
        }

        if (!empty($catIds)) {
            $product->id_category = $catIds;
            $product->updateCategories($product->id_category);
        }
    }

    /**
     * Adds a special/discounted price
     *
     * @param object $product
     * @return void
     */
    protected function addSpecialPrice($product)
    {
        if (!isset($product->discount_val)) {
            return ;
        }

        if (strpos($product->discount_val, '%') !== false) {
            $usePercent = true;
            $discount = ($product->discount_val/100);
        } else {
            $usePercent = false;
            $discount = $product->discount_val;
        }

        Db::getInstance()->query('
            DELETE FROM ' . _DB_PREFIX_ . 'specific_price
            WHERE id_product = ' . pSQL($product->id) . '
        ');

        $sp = new SpecificPrice();
        $sp->id_product = (int) $product->id;
        $sp->id_specific_price_rule = 0;
        $sp->id_shop = 1;
        $sp->id_currency = 0;
        $sp->id_country = 0;
        $sp->id_group = 0;
        $sp->price = -1;
        $sp->id_customer = 0;
        $sp->from_quantity = 1;
        $sp->from = '0000-00-00 00:00:00';
        $sp->to = '0000-00-00 00:00:00';
        $sp->reduction = $discount;
        $sp->reduction_type = ($usePercent) ? 'percentage' : 'amount';
        $sp->save();
    }

    /**
     * Deletes a product
     *
     * @param int $id
     * @return void
     */
    public function deleteProduct($id)
    {
        Db::getInstance()->query('
            DELETE FROM ' . _DB_PREFIX_ . 'product
            WHERE id_product = ' . (int) $id . '
        ');

        Db::getInstance()->query('
            DELETE FROM ' . _DB_PREFIX_ . 'product_lang
            WHERE id_product = ' . (int) $id . '
        ');
    }

    /**
     * Hides/Disables empty manufacturers
     *
     * @return void
     */
    public function disableUnusedManufacturers()
    {
        Db::getInstance()->query('
            UPDATE ' . _DB_PREFIX_ . 'manufacturer AS m
            SET m.active = 0;
        ');

        Db::getInstance()->query('
            UPDATE ' . _DB_PREFIX_ . 'manufacturer AS m
            SET m.active = 1
            WHERE m.id_manufacturer IN (SELECT p.id_manufacturer
            FROM ' . _DB_PREFIX_ . 'product_shop AS a
            LEFT JOIN ' . _DB_PREFIX_ . 'product AS p ON ( p.id_product = a.id_product )
            WHERE p.active =1
            GROUP BY p.id_manufacturer)
        ');
    }

    /**
     * Hides/Disables empty Categories
     *
     * @param bool $allowIntegerCategories Wether or not to disable categories with
     *                                     a number as a name
     * @return void
     */
    public function disableUnusedCategories($disableIntegerCategories = false)
    {
        Db::getInstance()->query('
            UPDATE ' . _DB_PREFIX_ . 'category AS c
            SET c.active = 0
            WHERE c.id_category > 2
        ');

        Db::getInstance()->query('
            UPDATE ' . _DB_PREFIX_ . 'category AS c
            SET c.active = 1
            WHERE c.id_category IN (
                SELECT p.id_category_default
                FROM ' . _DB_PREFIX_ . 'product_shop AS a
                LEFT JOIN ' . _DB_PREFIX_ . 'product AS p ON ( p.id_product = a.id_product )
                WHERE p.active =1
                GROUP BY p.id_category_default
            )
        ');

        Db::getInstance()->query('
            UPDATE ' . _DB_PREFIX_ . 'category AS mc
            SET mc.active = 1
            WHERE mc.id_category IN (
                SELECT c.id_category
                FROM ' . _DB_PREFIX_ . 'product AS p
                LEFT JOIN ' . _DB_PREFIX_ . 'category_product AS c ON ( c.id_product = p.id_product )
                WHERE p.active =1
                GROUP BY c.id_category
            )
        ');


        if ($disableIntegerCategories) {
            Db::getInstance()->query('
                UPDATE ' . _DB_PREFIX_ . 'category AS c
                SET c.active = 0
                WHERE c.id_category IN (
                    SELECT l.id_category
                    FROM ' . _DB_PREFIX_ . 'category_lang AS l
                    WHERE l.name REGEXP \'^[0-9]+$\'
                )
            ');
        }
    }

    /**
     * Hides/Disables free products
     *
     * @return void
     */
    public function disableFreeProducts()
    {
        Db::getInstance()->query('
            UPDATE ' . _DB_PREFIX_ . 'product AS p
            SET p.active = 0
            WHERE p.price = 0
        ');
    }
}

?>
