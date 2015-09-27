<?php
/**
 * helpfully array methods
 *
 * @package     BlueCollection
 * @subpackage  Helper
 * @author      Michał Adamiak    <chajr@bluetree.pl>
 * @copyright   chajr/bluetree
 */

namespace BlueCollection\Helper;

class ArrayHelper
{
    /**
     * allow to merge arrays with replace integer indexes
     * minimum is 2 arrays, number of given arrays is unlimited
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function arrayMerge(array $array1, array $array2)
    {
        $newArray   = [];
        $arrays     = func_get_args();

        foreach ($arrays as $array) {
            if (is_array($array)) {
                foreach ($array as $key => $val) {
                    $newArray[$key] = $val;
                }
            }
        }

        return $newArray;
    }

    /**
     * allow to merge arrays recursively with replace integer indexes
     * minimum is 2 arrays, number of given arrays is unlimited
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    public static function arrayMergeRecursive(array $array1, array $array2)
    {
        $newArray   = [];
        $arrays     = func_get_args();

        foreach ($arrays as $array) {
            if (is_array($array)) {
                foreach ($array as $key => $val) {
                    if (is_array($val) && array_key_exists($key, $newArray)) {
                        $newArray[$key] = self::arrayMergeRecursive(
                            $newArray[$key],
                            $val
                        );
                    } else {
                        $newArray[$key] = $val;
                    }
                }
            }
        }

        return $newArray;
    }
}
