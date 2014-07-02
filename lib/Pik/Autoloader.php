<?php
/**
 * Autoloader.php
 *
 * @package PrestashopImporterKit
 * @author  Michael Pratt <pratt@hablarmierda.net>
 * @link    http://www.michael-pratt.com/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * PSR-0 complaint autoloader library
 */
class Pik_Autoloader
{
    /**
     * Registers the autoloader as an SPL autoloader.
     *
     * @param bool $prepend Whether to prepend the autoloader or not.
     */
    public static function register($prepend = false)
    {
        if (version_compare(phpversion(), '5.3.0', '>=')) {
            spl_autoload_register(array(__CLASS__, 'autoload'), true, $prepend);
        } else {
            spl_autoload_register(array(__CLASS__, 'autoload'));
        }
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class A class name.
     */
    public static function autoload($class)
    {
        if (strpos($class, 'Pik') === false) {
            return;
        }

        $file = dirname(__FILE__) . '/../' . str_replace(array('_', "\0"), array('/', ''), $class) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}

?>
