<?php
/**
 * Images.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Image Importer
 */
class Pik_Importer_Images extends Pik_Importer_Adapter implements Pik_Importer_Interface
{
    /** inline {@inheritdoc} */
    public function import(array $data)
    {
        try {

            $idShopList = $this->getIdShopList($data);
            if (!$id = $this->getProductId($data)) {
                throw new InvalidArgumentException('Image: The product doesnt exist - ' . print_r($data, true));
            }

            if (!empty($data['images'])) {
                $this->addImages($id, (array) array_reverse($data['images']), $idShopList);
            }

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    protected function addImages($id, array $images = array(), array $idShopList = array())
    {
        $product = $this->getProduct($id);
        $product->deleteImages();

        foreach ($images as $url) {

            $hasImages = (bool) Image::getImages($this->config['id_lang'], $id);
            $image = new Image();
            $image->id_product = (int) $id;
            $image->position = Image::getHighestPosition($id) + 1;
            $image->cover = (!$hasImages) ? true : false;
            $image->add();

            if (!$status = $this->copyImage($id, $image->id, $url)) {
                $this->errors[] = 'Could not resize/convert Image ' . $url;
            } else {
                $image->associateTo($idShopList);
            }

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
     * @param string entity 'products' or 'categories'
     * @return void
     */
    protected function copyImage($id, $imageId, $url, $regenerate = true)
    {
        $status = false;
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $image = new Image($imageId);
        $path = $image->getPathForCreation();

        if (Tools::copy($url, $tmpfile)) {
            ImageManager::resize($tmpfile, $path . '.jpg');
            $types = ImageType::getImagesTypes('products');

            foreach ($types as $type) {
                clearstatcache();
                $dst = $path . '-' . stripslashes($type['name']) . '.jpg';
                if ($regenerate || !file_exists($dst)) {
                    ImageManager::resize($tmpfile, $dst, $type['width'], $type['height']);
                }
            }

            $status = true;
        }

        unlink($tmpfile);
        return $status;
    }
}
?>
