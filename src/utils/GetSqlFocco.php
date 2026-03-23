<?php

namespace src\utils;

use Exception;
use core\Database as db;

class GetSqlFocco
{
    private static $cache = [];

    public static function getSql(string $idsql): string
    {
        if ($idsql == '') {
            return '';
        }
        return self::buscaIdSql($idsql);
    }

    public static function buscaIdSql($idsql)
    {
        // Retorna do cache se já foi buscado
        if (isset(self::$cache[$idsql])) {
            return self::$cache[$idsql];
        }

        try {
            // Conecta diretamente ao Oracle para buscar o SQL
            $pdo = db::getInstance('focco');
            
            // Usa prepared statement para buscar o SQL (CLOB lido diretamente)
            $sql = "SELECT sql as sql_texto FROM focco3i.gazin_sqls WHERE idsql = :idsql";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':idsql', $idsql, \PDO::PARAM_STR);
            $stmt->execute();
            
            $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$resultado) {
                throw new \Exception(\sprintf('SQL não encontrado no Focco com idsql: %s', $idsql));
            }
            
            $sqlEncontrado = is_resource($resultado['SQL_TEXTO'])
                ? stream_get_contents($resultado['SQL_TEXTO'])
                : $resultado['SQL_TEXTO'];
            
            if (empty($sqlEncontrado)) {
                throw new \Exception('SQL encontrado mas está vazio');
            }
            
            $sqlTrimmed = trim($sqlEncontrado);
            self::$cache[$idsql] = $sqlTrimmed;
            return $sqlTrimmed;
            
        } catch (\Exception $e) {
            throw new Exception("Falha ao chamar SQL do Focco: " . $e->getMessage(), 1);
        }
    }
}
