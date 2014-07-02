<?php
/**
 * Attributes.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Attribute Importer
 */
class Pik_Importer_Attributes extends Pik_Importer_Adapter implements Pik_Importer_Interface
{
    protected $groups = array();
    protected $groupAttributes = array();
    protected $attributes = array();


    public function __construct(array $config = array(), array $filters = array())
    {
        parent::__construct($config, $filters);
        foreach ((array) AttributeGroup::getAttributesGroups($this->config['id_lang']) as $group) {
            $this->groups[$group['name']] = (int) $group['id_attribute_group'];
        }

        foreach ((array) Attribute::getAttributes($this->config['id_lang']) as $attribute) {
            $this->attributes[$attribute['attribute_group'].'_'.$attribute['name']] = (int) $attribute['id_attribute'];
        }
    }

    /** inline {@inheritdoc} */
    public function import(array $data)
    {
        try {
            if (!$id = $this->getProductId($data)) {
                throw new InvalidArgumentException('Attr: The product doesnt exist - ' . print_r($data, true));
            }

            if (!empty($data['features'])) {
                $this->addFeatures($id, $data['features']);
                unset($data['features']);
            }

            if (!empty($data['categories'])) {
                $cats = array_map('intval', $data['categories']);
                $product = $this->getProduct($id);
                $product->id_category = array((int) min($cats));
                $product->id_category_default = (int) min($cats);
                $product->addToCategories($cats);
                $product->save();
                //sleep(1);
                $product->save();
                unset($data['categories']);
            }

            $this->addSingleQuantity($id, $data);
            if (!empty($data['value']) && !empty($data['attribute'])) {
                $this->importAttributes($id, $data);
            }
        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }

    }

    protected function addFeatures($idProduct, $features)
    {
        $array = explode(',', $features);
        foreach ($array as $feat)
        {
            if (empty($feat)) {
                continue;
            }

            //$tab = explode(':', utf8_decode($feat));
            $tab = explode(':', $feat);
            $featName = isset($tab[0]) ? trim($tab[0]) : '';
            $featValue = isset($tab[1]) ? trim($tab[1]) : '';
            $position = isset($tab[2]) ? (int)$tab[2] : false;
            $custom = isset($tab[3]) ? (int)$tab[3] : false;

            if(!empty($featName) && !empty($featValue))
            {
                $idFeature = (int)Feature::addFeatureImport($featName, $position);
                $idFeatureValue = (int)FeatureValue::addFeatureValueImport($idFeature, $featValue, $idProduct, $this->config['id_lang'], $custom);
                Product::addFeatureProductImport($idProduct, $idFeature, $idFeatureValue);
            }
        }

        Feature::cleanPositions();
    }

    protected function addSingleQuantity($id, array &$data = array())
    {
        if (!empty($data['quantity']) && empty($data['value']) && empty($data['attribute'])) {
            StockAvailable::setQuantity($id, null, (int) $data['quantity']);
            unset($data['quantity']);
        }
    }

    protected function importAttributes($id, array $data)
    {
        $idShopList = $this->getIdShopList($data);
        $this->sortGroups($data, $idShopList);

        // inits attribute
        $id_image = null;
        $id_product_attribute = 0;
        $id_product_attribute_update = false;
        $attributes_to_add = array();

        // for each attribute
        $ref = implode('-', $data['value']);
        foreach ($data['value'] as $key => $attribute)
        {
            if (empty($attribute)) {
                continue;
            }

            $tabAttr = explode(':', $attribute);
            $attribute = trim($tabAttr[0]);

            // if position is filled
            if (isset($tabAttr[1])) {
                $position = trim($tabAttr[1]);
            } else {
                $position = false;
            }

            $product = $this->getProduct($id);
            $info = array(
                'reference' => (isset($data['reference']) ? $data['reference'] : 'REF-' . strtoupper(substr(md5($ref), 0, 10))),
                'supplier_reference' => (isset($data['supplier_reference']) ? $data['supplier_reference'] : ''),
                'ean13' => (isset($data['ean13']) ? $data['ean13'] : ''),
                'upc' => (isset($data['upc']) ? $data['upc'] : ''),
                'wholesale_price' => (isset($data['wholesale_price']) ? (int) $data['wholesale_price'] : 0),
                'price' => (isset($data['price']) ? (int) $data['price'] : 0),
                'ecotax' => (isset($data['ecotax']) ? (int) $data['ecotax'] : 0),
                'quantity' => (isset($data['quantity']) ? (int) $data['quantity'] : 0),
                'minimal_quantity' => (isset($data['minimal_quantity']) ? (int) $data['minimal_quantity'] : 1),
                'weight' => (isset($data['weight']) ? (int) $data['weight'] : 0),
                'default_on' => (isset($data['default_on']) ? (int) $data['default_on'] : 0),
            );

            if (isset($this->groupAttributes[$key]))
            {
                $group = $this->groupAttributes[$key]['group'];
                if (!isset($this->attributes[$group . '_' . $attribute]) && count($this->groupAttributes[$key]) >= 2)
                {
                    $id_attribute_group = $this->groupAttributes[$key]['id'];
                    $obj = new Attribute();
                    // sets the proper id (corresponding to the right key)
                    $obj->id_attribute_group = $this->groupAttributes[$key]['id'];
                    $obj->name[$this->config['id_lang']] = str_replace('\n', '', str_replace('\r', '', $attribute));
                    $obj->position = (!$position && isset($this->groups[$group])) ? Attribute::getHigherPosition($this->groups[$group]) + 1 : $position;
                    $res = $obj->add();
                    $obj->associateTo($idShopList);
                    $this->attributes[$group.'_'.$attribute] = $obj->id;
                }

                // if a reference is specified for this product, get the associate id_product_attribute to UPDATE
                if (isset($info['reference']) && !empty($info['reference']))
                {
                    $id_product_attribute = Combination::getIdByReference($product->id, strval($info['reference']));

                    // updates the attribute
                    if ($id_product_attribute)
                    {
                        // gets all the combinations of this product
                        $attribute_combinations = $product->getAttributeCombinations($this->config['id_lang']);
                        foreach ($attribute_combinations as $attribute_combination)
                        {
                            if ($id_product_attribute && in_array($id_product_attribute, $attribute_combination))
                            {
                                $product->updateAttribute(
                                    $id_product_attribute,
                                    (float)$info['wholesale_price'],
                                    (float)$info['price'],
                                    (float)$info['weight'],
                                    0,
                                    (float)$info['ecotax'],
                                    $id_image,
                                    strval($info['reference']),
                                    strval($info['ean13']),
                                    (int)$info['default_on'],
                                    0,
                                    strval($info['upc']),
                                    (int)$info['minimal_quantity'],
                                    0,
                                    null,
                                    $idShopList
                                );
                                $id_product_attribute_update = true;
                                if (isset($info['supplier_reference']) && !empty($info['supplier_reference']))
                                    $product->addSupplierReference($product->id_supplier, $id_product_attribute, $info['supplier_reference']);
                            }
                        }
                    }
                }

                // if no attribute reference is specified, creates a new one
                if (!$id_product_attribute)
                {
                    $id_product_attribute = $product->addCombinationEntity(
                        (float)$info['wholesale_price'],
                        (float)$info['price'],
                        (float)$info['weight'],
                        0,
                        (float)$info['ecotax'],
                        (int)$info['quantity'],
                        $id_image,
                        strval($info['reference']),
                        0,
                        strval($info['ean13']),
                        (int)$info['default_on'],
                        0,
                        strval($info['upc']),
                        (int)$info['minimal_quantity'],
                        $idShopList
                    );
                    if (!empty($info['supplier_reference']))
                        $product->addSupplierReference($product->id_supplier, $id_product_attribute, $info['supplier_reference']);
                }

                // fills our attributes array, in order to add the attributes to the product_attribute afterwards
                if(isset($this->attributes[$group.'_'.$attribute]))
                    $attributes_to_add[] = (int)$this->attributes[$group.'_'.$attribute];

                // after insertion, we clean attribute position and group attribute position
                $obj = new Attribute();
                $obj->cleanPositions((int) $this->groupAttributes[$key]['id'], false);
                AttributeGroup::cleanPositions();
            }
        }

        $product->checkDefaultAttributes();
        if (!$product->cache_default_attribute)
            Product::updateDefaultAttribute($product->id);
        if ($id_product_attribute)
        {
            // now adds the attributes in the attribute_combination table
            if (!$id_product_attribute_update)
            {
                Db::getInstance()->execute('
                    DELETE FROM '._DB_PREFIX_.'product_attribute_combination
                    WHERE id_product_attribute = '.(int)$id_product_attribute);
            }

            foreach ($attributes_to_add as $attribute_to_add)
            {
                Db::getInstance()->execute('
                    INSERT IGNORE INTO '._DB_PREFIX_.'product_attribute_combination (id_attribute, id_product_attribute)
                    VALUES ('.(int)$attribute_to_add.','.(int)$id_product_attribute.')');
            }

            StockAvailable::setQuantity($product->id, $id_product_attribute, (int)$info['quantity']);
        }
    }

    protected function sortGroups(array $data, array $idShopList)
    {
        foreach ($data['attribute'] as $key => $group)
        {
            if (empty($group)) {
                continue;
            }

            $this->groupAttributes[$key]['group'] = $group;
            $tabGroup = explode(':', $group);
            $group = trim($tabGroup[0]);
            if (!isset($tabGroup[1])) {
                $type = 'select';
            } else {
                $type = trim($tabGroup[1]);
            }

            // sets group
            $this->groupAttributes[$key]['group'] = $group;

            // if position is filled
            if (isset($tabGroup[2])) {
                $position = trim($tabGroup[2]);
            } else {
                $position = false;
            }

            if (!isset($this->groups[$group])) {
                $obj = new AttributeGroup();
                $obj->is_color_group = false;
                $obj->group_type = pSQL($type);
                $obj->name[$this->config['id_lang']] = $group;
                $obj->public_name[$this->config['id_lang']] = $group;
                $obj->position = (!$position) ? AttributeGroup::getHigherPosition() + 1 : $position;
                $obj->add();
                $obj->associateTo($idShopList);
                $this->groups[$group] = $obj->id;
                $this->groupAttributes[$key]['id'] = $obj->id;
            } else {
                $this->groupAttributes[$key]['id'] = $this->groups[$group];
            }
        }
    }

}
?>
