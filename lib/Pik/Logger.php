<?php
/**
 * Logger.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A very basic error logger
 */
class Pik_Logger
{
    protected $file;
    protected $msg = array();

    public function __construct($file)
    {
        $this->file = $file;
        $this->setMessage('LOG START: ' . date('Y-m-d H:i'));
    }

    public function setMessage($msg)
    {
        if (!empty($msg)) {
            $this->msg[] = $msg;
        }
    }

    public function setMessageArray(array $msg)
    {
        $this->msg = array_filter(array_merge($this->msg, $msg));
    }

    public function save()
    {
        if (count($this->msg) <= 2) {
            return ;
        }

        $msg = implode("\n", $this->msg);
        file_put_contents($this->file, $msg, FILE_APPEND);
        $this->msg = array();
    }

    public function __destruct()
    {
        $this->setMessage('============== End: ' . date('Y-m-d H:i') . ' ==============' . PHP_EOL . PHP_EOL);
        $this->save();
    }
}

?>
