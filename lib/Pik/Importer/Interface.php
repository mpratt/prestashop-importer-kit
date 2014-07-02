<?php
/**
 * Interface.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Adapter for importers
 */
interface Pik_Importer_Interface
{
    public function import(array $data);

    public function getErrors();
}
?>
