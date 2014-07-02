<?php
/**
 * ProgressBar.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Class responsable for outputting a progressbar
 */
class Pik_ProgressBar
{
    protected $total;
    protected $size;
    protected $eolChar;

    /** inline {@inheritdoc} */
    public function __construct($total, $size = 40, $eolChar = PHP_EOL)
    {
        $this->total = max(0, (int) $total);
        $this->size = max(40, (int) $size);
        $this->eolChar = $eolChar;
    }

    /** inline {@inheritdoc} */
    public function update($count, $time = 100000)
    {
        $statusBar = $this->getBar($count);
        echo "\r$statusBar  ";
        //flush();

        usleep($time);
        if($count == $this->total) {
            echo $this->eolChar . $this->eolChar;
        }
    }

    protected function getBar($count)
    {
        if ($count > $this->total) {
            return ;
        }

        $percent = (double) ($count/$this->total);
        $barLenght = floor($percent * $this->size);
        $statusBar = "[";
        $statusBar .= str_repeat("=", $barLenght);

        if($barLenght < $this->size){
            $statusBar .= ">";
            $statusBar .= str_repeat(" ", $this->size - $barLenght);

        } else {
            $statusBar .= "=";
        }

        $disp = number_format($percent * 100, 1);
        $statusBar.="] $disp%  $count/$this->total";

        return $statusBar;
    }
}

?>
