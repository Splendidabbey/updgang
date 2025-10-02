<?php
/**
 * UTF-8 library for XenForo compatibility
 * Simplified UTF-8 handling functions
 */

if (!defined('UTF8_CORE')) {
    define('UTF8_CORE', true);
}

/**
 * UTF-8 aware strlen
 */
function utf8_strlen($string) {
    return mb_strlen($string, 'UTF-8');
}

/**
 * UTF-8 aware substr
 */
function utf8_substr($string, $start, $length = null) {
    return mb_substr($string, $start, $length, 'UTF-8');
}

/**
 * UTF-8 aware strpos
 */
function utf8_strpos($haystack, $needle, $offset = 0) {
    return mb_strpos($haystack, $needle, $offset, 'UTF-8');
}

/**
 * UTF-8 aware strrpos
 */
function utf8_strrpos($haystack, $needle, $offset = 0) {
    return mb_strrpos($haystack, $needle, $offset, 'UTF-8');
}

/**
 * UTF-8 aware strtolower
 */
function utf8_strtolower($string) {
    return mb_strtolower($string, 'UTF-8');
}

/**
 * UTF-8 aware strtoupper
 */
function utf8_strtoupper($string) {
    return mb_strtoupper($string, 'UTF-8');
}

/**
 * UTF-8 aware ucfirst
 */
function utf8_ucfirst($string) {
    return mb_strtoupper(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($string, 1, null, 'UTF-8');
}

/**
 * UTF-8 aware lcfirst
 */
function utf8_lcfirst($string) {
    return mb_strtolower(mb_substr($string, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($string, 1, null, 'UTF-8');
}

/**
 * UTF-8 aware wordwrap
 */
function utf8_wordwrap($string, $width = 75, $break = "\n", $cut = false) {
    return mb_strimwidth($string, 0, $width, $break, 'UTF-8');
}

/**
 * UTF-8 aware trim
 */
function utf8_trim($string, $charlist = null) {
    if ($charlist === null) {
        return trim($string);
    }
    return trim($string, $charlist);
}

/**
 * UTF-8 aware ltrim
 */
function utf8_ltrim($string, $charlist = null) {
    if ($charlist === null) {
        return ltrim($string);
    }
    return ltrim($string, $charlist);
}

/**
 * UTF-8 aware rtrim
 */
function utf8_rtrim($string, $charlist = null) {
    if ($charlist === null) {
        return rtrim($string);
    }
    return rtrim($string, $charlist);
}

/**
 * UTF-8 aware explode
 */
function utf8_explode($delimiter, $string, $limit = null) {
    if ($limit === null) {
        return explode($delimiter, $string);
    }
    return explode($delimiter, $string, $limit);
}

/**
 * UTF-8 aware implode
 */
function utf8_implode($glue, $pieces) {
    return implode($glue, $pieces);
}

/**
 * UTF-8 aware str_split
 */
function utf8_str_split($string, $split_length = 1) {
    if ($split_length < 1) {
        return false;
    }
    $result = array();
    $length = mb_strlen($string, 'UTF-8');
    for ($i = 0; $i < $length; $i += $split_length) {
        $result[] = mb_substr($string, $i, $split_length, 'UTF-8');
    }
    return $result;
}

/**
 * UTF-8 aware strrev
 */
function utf8_strrev($string) {
    $length = mb_strlen($string, 'UTF-8');
    $result = '';
    for ($i = $length - 1; $i >= 0; $i--) {
        $result .= mb_substr($string, $i, 1, 'UTF-8');
    }
    return $result;
}

/**
 * UTF-8 aware str_pad
 */
function utf8_str_pad($input, $pad_length, $pad_string = ' ', $pad_type = STR_PAD_RIGHT) {
    $input_length = mb_strlen($input, 'UTF-8');
    if ($pad_length <= $input_length) {
        return $input;
    }
    
    $pad_string_length = mb_strlen($pad_string, 'UTF-8');
    $pad_length = $pad_length - $input_length;
    
    if ($pad_type == STR_PAD_RIGHT) {
        $repeat = ceil($pad_length / $pad_string_length);
        return $input . str_repeat($pad_string, $repeat);
    } elseif ($pad_type == STR_PAD_LEFT) {
        $repeat = ceil($pad_length / $pad_string_length);
        return str_repeat($pad_string, $repeat) . $input;
    } elseif ($pad_type == STR_PAD_BOTH) {
        $repeat = ceil($pad_length / $pad_string_length);
        $left = floor($repeat / 2);
        $right = $repeat - $left;
        return str_repeat($pad_string, $left) . $input . str_repeat($pad_string, $right);
    }
    
    return $input;
}
