<?php
/**
 * Adapter.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adapter with some boiler plate code that should be usefull for most
 * Importers
 */
abstract class Pik_Importer_Adapter
{
    /** @var array Array with configuration directives */
    protected $config = array();

    /** @var array Array With filters */
    protected $filters = array();

    /** @var array Array With errors */
    protected $errors = array();

    /** @var object Instance ov Validator */
    protected $validator;

    /**
     * Construct
     *
     * @param array $config
     * @param array $filters
     * @return void
     */
    public function __construct(array $config = array(), array $filters = array())
    {
        $_GET = array_merge((!empty($_GET) ? $_GET : array()), array('forceIDs' => 1));
        $this->validator = new Pik_Importer_Validator();

        $this->config = array_merge(array(
            'iso' => 'en',
            'id_lang' => (int) Configuration::get('PS_LANG_DEFAULT'),
        ), $config);

        $this->filters = array_merge(array(
            'active' => array($this->validator, 'getBoolean'),
            'tax_rate' => array($this->validator, 'getPrice'),
            'price_tex' => array($this->validator, 'getPrice'),
            'price_tin' => array($this->validator, 'getPrice'),
            'reduction_price' => array($this->validator, 'getPrice'),
            'reduction_percent' => array($this->validator, 'getPrice'),
            'wholesale_price' => array($this->validator, 'getPrice'),
            'ecotax' => array($this->validator, 'getPrice'),
            'name' => array($this->validator, 'getName'),
            'description' => array($this->validator, 'createMultiLangField'),
            'description_short' => array($this->validator, 'sumarizeMultiLangField'),
            'meta_title' => array($this->validator, 'createMultiLangField'),
            'meta_keywords' => array($this->validator, 'createMultiLangField'),
            'meta_description' => array($this->validator, 'createMultiLangField'),
            'link_rewrite' => array($this->validator, 'createMultiLangField'),
            'available_now' => array($this->validator, 'createMultiLangField'),
            'available_later' => array($this->validator, 'createMultiLangField'),
            'quantity' => array($this->validator, 'getInt'),
            'online_only' => array($this->validator, 'getBoolean'),
        ), $filters);
    }

    /**
     * Creates/Instantiates a new Product Object
     *
     * @param int $id The Id of the product
     * @return object
     */
    public function getProduct($id = null)
    {
        if (!empty($id)) {
            $product = new Product($id);
            $product->force_id = true;
            $product->id = $id;
            $product->id_category = (array) $product->getCategories();
        } else {
            $product = new Product();
        }

        $address = Context::getContext()->shop->getAddress();
        $tax_manager = TaxManagerFactory::getManager($address, $product->id_tax_rules_group);
        $product_tax_calculator = $tax_manager->getTaxCalculator();
        $taxRate = $product_tax_calculator->getTotalRate();

        $defaultValues = array(
            /*'id_category' => array((int) Configuration::get('PS_HOME_CATEGORY')),
            'id_category_default' => (int) Configuration::get('PS_HOME_CATEGORY'),*/
            'id_category' => array(),
            'id_category_default' => '',
            'name' => array($this->config['id_lang'] => ''),
            'description_short' => array($this->config['id_lang'] => ''),
            'description' => array($this->config['id_lang'] => ''),
            'link_rewrite' => array($this->config['id_lang'] => ''),
            'active' => '1',
            'width' => 0.000000,
            'height' => 0.000000,
            'depth' => 0.000000,
            'weight' => 0.000000,
            'visibility' => 'both',
            'additional_shipping_cost' => 0.00,
            'unit_price_ratio' => 0.000000,
            'quantity' => 0,
            'minimal_quantity' => 1,
            'price' => 0,
            'id_tax_rules_group' => 0,
            'tax_rate' => $taxRate,
            'online_only' => 0,
            'condition' => 'new',
            'available_date' => date('Y-m-d'),
            'date_add' => date('Y-m-d H:i:s'),
            'customizable' => 0,
            'uploadable_files' => 0,
            'text_fields' => 0,
            'out_of_stock' => '2',
            'advanced_stock_management' => '0',
            'shop' => 1,
            'id_shop_default' => 1,
            'id_shop_list' => array(),
        );

        if ($this->productExists(array('id' => $id))) {
            $defaultValues = array_merge($defaultValues, (array) $this->getProductDataDb($id));
            $product->loadStockData();
        }

        foreach ($defaultValues as $k => $v) {
            if (empty($product->$k)) {
                $product->$k = $v;
            }
        }

        return $product;
    }

    /**
     * Gets product data from the db, based on the product id
     *
     * @param int $id
     * @return array
     */
    protected function getProductDataDb($id)
    {
        $data = Db::getInstance()->getRow('
            SELECT
                p.id_supplier AS supplier, p.id_manufacturer AS manufacturer, p.id_category_default, c.name AS category, p.id_shop_default,
                p.id_tax_rules_group, p.price AS price_tex, p.reference, p.location, p.width, p.height, p.depth, p.weight, p.condition,
                p.show_price, p.indexed, p.date_add, p.date_upd,
                pl.description, pl.description_short, pl.link_rewrite, pl.meta_description, pl.meta_keywords, pl.meta_title
            FROM ' . _DB_PREFIX_ . 'product p
            ' . Shop::addSqlAssociation('product', 'p') . '
            LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (pl.id_product = p.id_product)
            LEFT JOIN ' . _DB_PREFIX_ . 'category_lang c ON (c.id_category = p.id_category_default)
            WHERE p.id_product = "' . pSQL($id) .'"
        ');

        $data['id_category'] = array($data['id_category_default']);
        $data['id_category_default'] = $data['id_category_default'];
        $data['description'] = array($this->config['id_lang'] => $data['description']);
        $data['description_short'] = array($this->config['id_lang'] => $data['description_short']);
        $data['meta_description'] = array($this->config['id_lang'] => $data['meta_description']);
        $data['meta_title'] = array($this->config['id_lang'] => $data['meta_title']);
        $data['meta_keywords'] = array($this->config['id_lang'] => $data['meta_keywords']);
        $data['link_rewrite'] = array($this->config['id_lang'] => $data['link_rewrite']);

        return (array) $data;
    }

    /**
     * Merges an array with properties and the properties of an object
     *
     * @param object $object
     * @param array $data
     * @return object
     */
    protected function mergeData($object, array $data)
    {
        foreach ($data as $k => $v) {
            if (!isset($this->filters[$k])) {
                $object->{$k} = $v;
                continue ;
            }

            $result = call_user_func($this->filters[$k], $v);
            if (is_array($result)) {
                $idLang = Language::getIdByIso($this->config['iso']);
                foreach ($result as $tmpId => $value) {
                    if (empty($object->{$k}[$tmpId]) || $tmpId == $idLang) {
                        $object->{$k}[$tmpId] = $value;
                    }
                }
            } else {
                $object->{$k} = $result;
            }
        }

        return $object;
    }

    /**
     * Checks if a product might exist based on the given data
     *
     * @param array $data Associative array
     * @return bool
     */
    public function productExists(array $data)
    {
        return (bool) $this->getProductId($data);
    }

    /**
     * Returns the Id of a product. It also makes sure that
     * the product exists in the database. It either needs the reference
     * of the product or its Id
     *
     * @param array $data Associative array
     * @return bool
     */
    protected function getProductId(array $data)
    {
        if (!empty($data['id']) && Product::existsInDatabase($data['id'], 'product')) {
            return $data['id'];
        } else if (!empty($data['reference'])) {
            $return = Db::getInstance()->getRow('
                SELECT p.`id_product`
                FROM `'. _DB_PREFIX_ .'product` p
                '. Shop::addSqlAssociation('product', 'p') .'
                WHERE p.`reference` = "'. pSQL($data['reference']) .'"
            ');

            if (isset($return['id_product'])) {
                return $return['id_product'];
            }
        }

        return null;
    }

    /**
     * Returns an array with available Shop ids
     *
     * @param array $data
     * @return array
     */
    protected function getIdShopList(array $data)
    {
        if (empty($data['shop'])) {
            return array();
        }

        $list = array();
        foreach ((array) $data['shop'] as $shop) {
            if (!is_numeric($shop)) {
                $list[] = Shop::getIdByName($shop);
            } else {
                $list[] = $shop;
            }
        }

        return $list;
    }

    /** inline {@inheritdoc} */
    public function getErrors()
    {
        return $this->errors;
    }
}
?>
