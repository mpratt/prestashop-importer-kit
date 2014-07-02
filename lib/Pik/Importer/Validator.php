<?php
/**
 * Validator.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A simple validator
 */
class Pik_Importer_Validator
{
    /**
     * Returns a boolean value from a mixed var
     *
     * @param mixed $field
     * @return bool
     */
    public function getBoolean($field)
    {
        return (boolean) $field;
    }

    /**
     * Returns a float number from a price
     *
     * @param mixed $field
     * @return float
     */
    public function getPrice($field)
    {
        $t = array(',' => '.', '%' => '');
        return (float) str_replace(array_keys($t), array_values($t), $field);
    }

    /**
     * Returns an integer from a mixed var
     *
     * @param mixed $field
     * @return int
     */
    public function getInt($field)
    {
        return intval($field);
    }

    /**
     * Returns an array with language fields
     *
     * @param mixed $fields
     * @return array
     */
    public function createMultiLangField($field)
    {
        $res = array();
        $languages = Language::getLanguages(false);
        foreach ($languages as $lang) {
            $res[$lang['id_lang']] = $field;
        }

        return $res;
    }

    /**
     * Normalizes the name of a product
     *
     * @param string $field
     * @return array
     */
    public function getName($field)
    {
        $field = str_replace('#', 'No.', $field);
        return $this->createMultiLangField(str_replace(array(';', '=', '{', '}'), '', $field));
    }

    /**
     * Sumarizes a given text
     *
     * @param string $desc
     * @param int $limit
     * @return string
     */
    public function sumarize($desc, $limit = 380)
    {
        $desc = trim(strip_tags($desc));
        $desc = preg_replace('~(\r\n?)|(\t)~', ' ', $desc);
        $desc = preg_replace('~^[ ]+$~', ' ', $desc);

        list($short) = explode("\n", wordwrap($desc, $limit));
        $lastDot = strrpos($short, '.');
        if ($lastDot !== false) {
            return substr($short, 0, $lastDot);
        }

        return $short;
    }

    /**
     * Sumarizes a multi language field
     *
     * @param string $text
     * @return array
     */
    public function sumarizeMultiLangField($text)
    {
        $text = $this->sumarize($text);
        return $this->createMultiLangField($text);
    }
}
?>
