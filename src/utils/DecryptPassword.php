<?php

namespace src\utils;

class DecryptPassword
{
    public static function decrypt(
        $Strg = '',
        $Password = '#%@#'
    ) {
        $b='';
        $s='';
        $i=0;
        $j=0;
        $A1=0;
        $A3=0;
        $p='';
        $j = 0;
        $s = '';
        for ($i = 0; $i < strlen($Password); $i++) {
            $c = $Password[$i];
            $p .= ((int) (ord($c)));
        }
        $i = 4;
        while ($i < strlen($Strg)) {
            $A1 = ord(substr($p, $j, 1));
            $j++;
            if ($j >= strlen($p)) {
                $j = 0;
            }
            $b  = substr($Strg, $i, 2);
            $A3 = @hexdec(($b));
            $s  .= chr(($A1 ^ $A3));
            $i += 2;
        }
        return $s;
    }
}
 