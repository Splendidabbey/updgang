<?php
namespace AddonFlare\AwardSystem;

class IDs
{
    public static $prefix = 'getv';
    public static function get($id)
    {
        return array_values(self::getSetA($id))[$id];
    }
    public static function getSetA($ID1 = "3f42a27a68c0f048684773727e7303c0", $ID2 = "302dbe2ed449cbdf7da0019181bdf868", $ID3 = "7c34753d106ed2959beb737cab8ffe5f")
    {
        return [
            '$ID1' => "afbce9d8150b71a041dc6986163098d7",
            '$ID2' => "6e5be312a6a7f60be231a79f812132cc",
            '$ID3' => "271b4e73141aae7c18ed417815182ec0",
        ];
    }
    public static function getSetB($setType = null)
    {
        $ID1 = "a4efb4f68691dd8dbe69059d81c8b31e";
        $ID2 = strtolower(preg_replace(self::getSetC(7), "", trim(\XF::app()->container(self::getSetC(1, null, 9))->offsetGet(self::getSetC(1, 8)), "/")));
        $ID = '$ID1';
        return !isset($setType) ? md5(self::getSetA("6268ad04de843cb0214277dd5c68b3fb", "2a26c0295eaa8dad8609b0a59fa1ef87", "df7ea240e2627644a7fc362486041c20")[$ID] . $ID2 . Listener::ID . Listener::ID_NUM) : ($ID2);
    }
    public static function getSetC($s, $n = null, $o = 0, $k = true, $f = "implode")
    {
        return call_user_func_array($f, ["", array_map(self::getSetD(), array_slice(self::getID($s, $k), $o, $n))]);
    }
    public static function getSetD()
    {
        return function($x) {return chr("{$x}");};
    }
    public static function hashes()
    {
        return !!((md5(self::get(1) . self::get(0) . self::getSetB('') . Listener::ID . Listener::ID_NUM) == self::get(2)));
    }
    public static function getF()
    {
        $s = '%s%s%s%s%s';

        $e = \XF::escapeString(nl2br(str_repeat("\n", 2*3)) . self::getG(1), self::getSetC(6));

        $sets = [

            self::getSetC(4, null, 0),
            $e,
            self::getSetC(5),
            $e,
            self::getSetC(11),
        ];

        return vsprintf($s, $sets);
    }
    protected static function getG($set)
    {
        return self::getH(self::getSetC(1, null, 0, false));
    }
    protected static function getH($k)
    {
        return call_user_func_array(self::getSetC(3, null, 0), [$k]);
    }
    public static function getV($v)
    {
        if ($v instanceof \XF\Mvc\Entity\Entity)
        {
            $ret = (md5(self::getSetB() . self::get(2) . self::getSetB('')) == self::get(0));
        }
        else
        {
            self::CR($v, $o);
            $s = self::getSetC(12, null);
            $ret = ((self::getSetB() == self::get(1)) && (self::hashes() || !empty($v->{$s}[Listener::ID])));
        }

        return $ret;
    }
    public static function CR($t, &$o)
    {
        do
        {
            $k = self::getSetC(12, null);
            if (!isset($t->{$k}))
            {
                $t->{$k} = [];
            }
            while (self::hashes()) {break(2);}

            $t->{$k}[Listener::ID] = Listener::TITLE;

            asort($t->{$k});

            $escape = false;
            $lc = $t->fnProperty($t, $escape, 'publicFooterLink--color');

            if (!$lc) $lc = 'inherit';

            $str = self::getSetC(8) . implode(', ', $t->{$k}) . self::getSetC(9) . $lc . self::getSetC(10);

            $re = '/<div data-af-cp.+?<\/div>/i';

            if (preg_match($re, $o))
            {
                $o = preg_replace($re, $str, $o, 1);
            }
            else
            {
                $o .= $str;
            }
        }
        while (false);
    }

    public static function getID($n, $s = true)
    {
        $n = "IDS{$n}";
        return $s ? self::$$n : Listener::$$n;
    }
    protected static $IDS1 = [0 => 98, 1 => 111, 2 => 97, 3 => 114, 4 => 100, 5 => 85, 6 => 114, 7 => 108, 8 => 45, 9 => 111, 10 => 112, 11 => 116, 12 => 105, 13 => 111, 14 => 110, 15 => 115,];
    protected static $IDS2 = [0 => 105, 1 => 110, 2 => 108, 3 => 105, 4 => 110, 5 => 101, 6 => 74, 7 => 115,];
    protected static $IDS3 = [0 => 92, 1 => 88, 2 => 70, 3 => 58, 4 => 58, 5 => 112, 6 => 104, 7 => 114, 8 => 97, 9 => 115, 10 => 101,];
    protected static $IDS4 = [118, 97, 114, 32, 109, 97, 105, 110, 77, 101, 115, 115, 97, 103, 101, 49, 32, 61, 32, 109, 97, 105, 110, 77, 101, 115, 115, 97, 103, 101, 49, 32, 124, 124, 32, 40, 36, 40, 34, 46, 112, 45, 98, 111, 100, 121, 45, 109, 97, 105, 110, 34, 41, 46, 98, 101, 102, 111, 114, 101, 40, 34, 60, 100, 105, 118, 62];
    protected static $IDS5 = [60, 47, 100, 105, 118, 62, 34, 41, 32, 38, 38, 32, 36, 40, 34, 46, 112, 45, 99, 111, 110, 116, 101, 110, 116, 34, 41, 46, 104, 116, 109, 108, 40, 34, 60, 100, 105, 118, 62];
    protected static $IDS6 = [0 => 106, 1 => 115];
    protected static $IDS7 = [35, 104, 116, 116, 112, 115, 63, 58, 92, 47, 92, 47, 40, 119, 119, 119, 92, 46, 41, 63, 35, 105];
    protected static $IDS8 = [60, 100, 105, 118, 32, 100, 97, 116, 97, 45, 97, 102, 45, 99, 112, 32, 115, 116, 121, 108, 101, 61, 34, 109, 97, 114, 103, 105, 110, 58, 32, 48, 32, 97, 117, 116, 111, 59, 34, 62, 60, 97, 32, 99, 108, 97, 115, 115, 61, 34, 117, 45, 99, 111, 110, 99, 101, 97, 108, 101, 100, 34, 32, 116, 97, 114, 103, 101, 116, 61, 34, 95, 98, 108, 97, 110, 107, 34, 32, 104, 114, 101, 102, 61, 34, 104, 116, 116, 112, 115, 58, 47, 47, 119, 119, 119, 46, 97, 100, 100, 111, 110, 102, 108, 97, 114, 101, 46, 99, 111, 109, 34, 62];
    protected static $IDS9 = [32, 98, 121, 32, 60, 115, 112, 97, 110, 32, 115, 116, 121, 108, 101, 61, 34, 99, 111, 108, 111, 114, 58];
    protected static $IDS10 = [59, 34, 62, 65, 100, 100, 111, 110, 70, 108, 97, 114, 101, 32, 45, 32, 80, 114, 101, 109, 105, 117, 109, 32, 88, 70, 50, 32, 65, 100, 100, 111, 110, 115, 60, 47, 115, 112, 97, 110, 62, 60, 47, 97, 62, 60, 47, 100, 105, 118, 62];
    protected static $IDS11 = [0 => 60, 1 => 47, 2 => 100, 3 => 105, 4 => 118, 5 => 62, 6 => 34, 7 => 41, 8 => 41, 9 => 59];
    protected static $IDS12 = [0 => 65, 1 => 70, 2 => 95, 3 => 102, 4 => 111, 5 => 111, 6 => 116, 7 => 101, 8 => 114];
}
