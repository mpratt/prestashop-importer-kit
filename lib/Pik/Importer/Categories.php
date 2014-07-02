<?php
/**
 * Categories.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Categories Importer
 */
class Pik_Importer_Categories extends Pik_Importer_Adapter implements Pik_Importer_Interface
{
    protected $cats = array();

    /** inline {@inheritdoc} */
    public function import(array $data)
    {
        $data = array_merge(array(
            'id' => null,
            'parent' => null,
            'active' => 1,
            'name' => null,
        ), $data);

        try {

            if (!$data['id'] && !$data['name']) {
                throw new Exception('Misssing name on category');
            } else if (in_array($data['id'], array(Configuration::get('PS_HOME_CATEGORY'), Configuration::get('PS_ROOT_CATEGORY')))) {
                throw new Exception('The category ID cannot be the same as the Root category ID or the Home category ID.');
            }

            $res = false;
            $category = $this->createCategory($data['id'], $data['name'], $data['parent'], $data['active']);
            if (!empty($data['image'])) {
                $this->copyImg($data['id'], $data['image']);
            }

            if (!$category) {
                throw new Exception('The category could not be added' . print_r($data, true));
            }

            // If id category AND id category already in base, trying to update
            $categories_home_root = array(Configuration::get('PS_ROOT_CATEGORY'), Configuration::get('PS_HOME_CATEGORY'));
            if ($category->id && $category->categoryExists($category->id) && !in_array($category->id, $categories_home_root)) {
                $res = $category->update();
            }

            // If no id_category or update failed
            if (!$res) {
                $category->add();
            }

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }

        if (mt_rand(0, 10) > 80) {
            Category::regenerateEntireNtree();
        }
    }

    /**
     * Creates a new category
     */
    public function createCategory($id = null, $name, $parent = null, $active = 1)
    {
        if (is_numeric($id) && Category::categoryExists((int) $id)) {
            return new Category((int) $id);
        } else if (trim($name) !== '') {

            if (strtolower($name) == 'category') {
                return null;
            }

            if (!empty($parent) && $category = Category::searchByNameAndParentCategoryId($this->config['iso'], trim($name), $parent)) {
                return new Category($category['id_category']);
            } else if (empty($parent) && $category = Category::searchByName($this->config['iso'], trim($name), true)) {
                return new Category($category['id_category']);
            }

            if (preg_match('~^[0-9]*$~', $name)) {
                return null;
            }
        }

        if (empty($id)) {
            $category = new Category();
        } else {
            $category = new Category((int) $id);
            $category->force_id = true;
            $category->id = (int) $id;
        }

        if (empty($parent)) {
            $category->id_parent = (int) Configuration::get('PS_HOME_CATEGORY');
        } else {
            if ($parentId = $this->getCategoryId($parent)) {
                $category->id_parent = $parentId;
            } else {
                throw new Exception(sprintf('The parent category %s doesnt exist', $parent));
            }
        }

        if (!Shop::isFeatureActive()) {
            $category->id_shop_default = 1;
        } else {
            $category->id_shop_default = (int) Context::getContext()->shop->id;
        }

        if (empty($name)) {
            $name = $id;
        }

        $category->active = $active;
        $category->name = $this->validator->createMultiLangField(trim($name));
        $link = Tools::link_rewrite($category->name[$this->config['id_lang']]);
        $category->link_rewrite = $this->validator->createMultiLangField($link);

        $category->add();
        return $category;;
    }

    public function getCategoryId($id)
    {
        if (is_numeric($id) && Category::existsInDatabase((int) $id, 'category')) {
            return (int) $id;
        } else if (!is_numeric($id) && $parent = Category::searchByName($this->config['id_lang'], $id, true)) {
            return $parent['id_category'];
        } else {
            return null;
        }
    }

    /**
     * copyImg copy an image located in $url and save it in a path
     * according to $entity->$id_entity .
     * $id_image is used if we need to add a watermark
     *
     * @param int $id_entity id of product or category (set in entity)
     * @param int $id_image (default null) id of the image if watermark enabled.
     * @param string $url path or url to use
     * @return void
     */
    protected static function copyImg($id, $url, $regenerate = true)
    {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));
        $path = _PS_CAT_IMG_DIR_.(int)$id;
        $url = str_replace(' ', '%20', trim($url));

        // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
        if (!ImageManager::checkImageMemoryLimit($url)) {
            return false;
        }

        // 'file_exists' doesn't work on distant file, and getimagesize make the import slower.
        // Just hide the warning, the traitment will be the same.
        if (Tools::copy($url, $tmpfile)) {
            ImageManager::resize($tmpfile, $path.'.jpg');
            $images_types = ImageType::getImagesTypes('categories');

            foreach ($images_types as $image_type) {
                ImageManager::resize($tmpfile, $path.'-'.stripslashes($image_type['name']).'.jpg', $image_type['width'], $image_type['height']);
            }
        }
        else
        {
            unlink($tmpfile);
            return false;
        }

        unlink($tmpfile);
        return true;
    }

    public function getChildProductIds($id, $number = 5, $random = true, $active = true)
    {
        $front = true;
        $context = Context::getContext();
        $order_by = 'position';
        $order_by_prefix = 'cp';
        $order_way = 'ASC';

        $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, MAX(product_attribute_shop.id_product_attribute) id_product_attribute, product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, pl.`description`, pl.`description_short`, pl.`available_now`,
            pl.`available_later`, pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, MAX(image_shop.`id_image`) id_image,
            il.`legend`, m.`name` AS manufacturer_name, cl.`name` AS category_default,
            DATEDIFF(product_shop.`date_add`, DATE_SUB(NOW(),
                    INTERVAL '.(Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20).'
                    DAY)) > 0 AS new, product_shop.price AS orderprice
                    FROM `'._DB_PREFIX_.'category_product` cp
                    LEFT JOIN `'._DB_PREFIX_.'product` p
                    ON p.`id_product` = cp.`id_product`
                    '.Shop::addSqlAssociation('product', 'p').'
                    LEFT JOIN `'._DB_PREFIX_.'product_attribute` pa
                    ON (p.`id_product` = pa.`id_product`)
                    '.Shop::addSqlAssociation('product_attribute', 'pa', false, 'product_attribute_shop.`default_on` = 1').'
                    '.Product::sqlStock('p', 'product_attribute_shop', false, $context->shop).'
                    LEFT JOIN `'._DB_PREFIX_.'category_lang` cl
                    ON (product_shop.`id_category_default` = cl.`id_category`
                    AND cl.`id_lang` = '.(int)$this->config['id_lang'].Shop::addSqlRestrictionOnLang('cl').')
                    LEFT JOIN `'._DB_PREFIX_.'product_lang` pl
                    ON (p.`id_product` = pl.`id_product`
                    AND pl.`id_lang` = '.(int)$this->config['id_lang'].Shop::addSqlRestrictionOnLang('pl').')
                    LEFT JOIN `'._DB_PREFIX_.'image` i
                    ON (i.`id_product` = p.`id_product`)'.
                    Shop::addSqlAssociation('image', 'i', false, 'image_shop.cover=1').'
                    LEFT JOIN `'._DB_PREFIX_.'image_lang` il
                    ON (image_shop.`id_image` = il.`id_image`
                    AND il.`id_lang` = '.(int)$this->config['id_lang'].')
                    LEFT JOIN `'._DB_PREFIX_.'manufacturer` m
                    ON m.`id_manufacturer` = p.`id_manufacturer`
                    WHERE product_shop.`id_shop` = '.(int)$context->shop->id.'
                    AND cp.`id_category` = '.(int)$id
                    .($active ? ' AND product_shop.`active` = 1' : '')
                    .($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '')
                    .' GROUP BY product_shop.id_product';

        if ($random === true)
        {
            $sql .= ' ORDER BY RAND()';
            $sql .= ' LIMIT 0, '.(int)$number;
        }
        else
            $sql .= ' ORDER BY '.(isset($order_by_prefix) ? $order_by_prefix.'.' : '').'`'.pSQL($order_by).'` '.pSQL($order_way).'
            LIMIT 0,'.(int) $number;

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    public function setAllCategoryImages()
    {
        $cats = Db::getInstance()->ExecuteS('
            SELECT id_category
            FROM ' . _DB_PREFIX_ . 'category
            WHERE id_category > 3
            ');

        foreach ($cats as $cat) {
            $ids = $this->getChildProductIds($cat['id_category'], 20);
            $productId = end($ids);

            if (empty($productId['id_product'])) {
                $this->errors[] = 'The category ' . $cat['id_category'] . ' is empty';
                continue ;
            }

            $imageId = Product::getCover($productId['id_product']);
            if (count($imageId) > 0) {
                $image = new Image($imageId['id_image']);
                $url = $image->getPathForCreation() . '.jpg';
                if (file_exists($url)) {
                    $this->import(array(
                        'image' => $url,
                        'id' => $cat['id_category']
                    ));
                } else {
                    $this->errors[] = 'The url ' . $url . ' doesnt exist';
                }
            } else {
                $this->errors[] = 'The product ' . $productId['id_product'] . ' doesnt have images';
            }
        }
    }

}
?>
