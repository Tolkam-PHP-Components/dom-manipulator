<?php declare(strict_types=1);

namespace Tolkam\DOM\Manipulator;

use DOMDocument;
use DOMNode;
use const LIBXML_VERSION;

class Util
{
    /**
     * Removes newlines from string and minimize whitespace
     *
     * @param string $str
     *
     * @return string
     */
    public static function trimNewlines(string $str): string
    {
        $str = str_replace("\n", ' ', $str);
        $str = str_replace("\r", ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        
        return trim($str);
    }
    
    /**
     * Converts CSS string to array
     *
     * @param string $css list of CSS properties separated by ;
     *
     * @return array name=>value pairs of CSS properties
     */
    public static function cssStringToArray(string $css): array
    {
        $statements = explode(';', preg_replace('/\s+/s', ' ', $css));
        $styles = [];
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ('' === $statement) {
                continue;
            }
            $p = strpos($statement, ':');
            if ($p <= 0) {
                continue;
            } // invalid statement, just ignore it
            $key = trim(substr($statement, 0, $p));
            $value = trim(substr($statement, $p + 1));
            $styles[$key] = $value;
        }
        
        return $styles;
    }
    
    /**
     * Converts CSS name->value array to string
     *
     * @param array $array name=>value pairs of CSS properties
     *
     * @return string list of CSS properties separated by ;
     */
    public static function cssArrayToString(array $array): string
    {
        $styles = '';
        foreach ($array as $key => $value) {
            $styles .= $key . ': ' . $value . ';';
        }
        
        return $styles;
    }
    
    /**
     * Gets a body element from an HTML fragment
     *
     * @param string $html A fragment of HTML code
     * @param string $charset
     *
     * @return DOMNode
     */
    public static function getBodyNodeFromHtmlFragment(string $html, $charset = 'UTF-8'): DOMNode
    {
        $unsafeLibXml = LIBXML_VERSION < 20900;
        $html = '<html><body>' . $html . '</body></html>';
        $current = libxml_use_internal_errors(true);
        if ($unsafeLibXml) {
            $disableEntities = libxml_disable_entity_loader(true);
        }
        $d = new DOMDocument('1.0', $charset);
        $d->validateOnParse = true;
        if (function_exists('mb_convert_encoding') && in_array(
                strtolower($charset),
                array_map('strtolower', mb_list_encodings())
            )
        ) {
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', $charset);
        }
        @$d->loadHTML($html);
        libxml_use_internal_errors($current);
        if ($unsafeLibXml) {
            libxml_disable_entity_loader($disableEntities);
        }
        
        return $d->getElementsByTagName('body')->item(0);
    }
}
