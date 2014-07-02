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
 * This class reads CSV files and allows you to Iterate over
 * the values using a foreach loop.
 *
 * Why wrap the SplFileObject inside another Iterator?
 * Because I needed to associate the column names to the values, in
 * order to make the whole process a little bit more readable.
 *
 * @usage:
 *      $reader = new CSVReader('file.csv');
 *      foreach ($reader as $r) {
 *          print_r($r);
 *      }
 */
interface Pik_Reader_Interface extends \Iterator
{
    public function count();
}
?>
