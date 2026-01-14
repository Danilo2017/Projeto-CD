<?php

namespace src\utils;

class Encode
{
    const ENCODINGS = [
        'UTF-8',
        'ISO-8859-1',
        'Windows-1252',  // Muito similar a ISO-8859-1, mas com algumas diferenças
        'ASCII',
        'GB18030',       // Chinês simplificado
        'GB2312',        // Versão mais antiga do chinês simplificado
        'BIG5',          // Chinês tradicional
        'Shift_JIS',     // Japonês
        'EUC-JP',        // Japonês
        'ISO-2022-JP',   // Japonês (e-mails/sistemas legados)
        'EUC-KR',        // Coreano
        'Windows-1251',  // Cirílico (russo)
        'KOI8-R',        // Cirílico (mais antigo, mas ainda encontrado)
    ];

    /**
     * Verifica se a string é UTF-8 válido usando preg_match
     */
    private static function isValidUTF8(string $str): bool
    {
        // Se for UTF-8 válido, preg_match('//u', $str) retorna 1
        return (preg_match('//u', $str) === 1);
    }

    private static function isValidUTF8Iconv(string $str): bool
    {
        // Converte para UTF-8 ignorando bytes ilegais
        $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
        // Se após ignorar caracteres inválidos a string for igual, é UTF-8
        return ($converted === $str);
    }


    /**
     * Converte a string para UTF-8 caso não esteja em UTF-8 válido
     */
    public static function encodeUTF8(string $str): string
    {
        // 1) Se já for UTF-8 válido, não converte
        if (self::isValidUTF8($str)) {
            return $str;
        }

        if (self::isValidUTF8Iconv($str)) {
            return $str;
        }

        // 2) Tenta detectar a codificação de origem
        $encodingDetectada = mb_detect_encoding($str, self::ENCODINGS, true);

        echo "Encoding detectada: $encodingDetectada\n";
        if ($encodingDetectada === false) {
            $encodingDetectada = 'Windows-1252';
        } elseif ($encodingDetectada === 'ISO-8859-1') {
            // Muitos bytes de Windows-1252 são interpretados como ISO-8859-1
            $encodingDetectada = 'Windows-1252';
        }

        // 3) Converte para UTF-8
        return mb_convert_encoding($str, 'UTF-8', $encodingDetectada);
    }
}
