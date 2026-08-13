<?php

namespace src\utils;

use Exception;
use core\Database as db;

class GetSqlFocco
{
    private static array $cache = [];
    private static string $cacheDir = '';

    private static function cacheDir(): string
    {
        if (self::$cacheDir === '') {
            self::$cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'focco_sql_cache';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
        return self::$cacheDir;
    }

    private static function fileGet(string $idsql): ?string
    {
        $file = self::cacheDir() . DIRECTORY_SEPARATOR . md5($idsql) . '.sql';
        if (!is_file($file)) return null;
        $data = @unserialize(@file_get_contents($file));
        if (!is_array($data) || time() > $data['exp']) {
            @unlink($file);
            return null;
        }
        return $data['val'];
    }

    private static function fileSet(string $idsql, string $sql): void
    {
        $file = self::cacheDir() . DIRECTORY_SEPARATOR . md5($idsql) . '.sql';
        @file_put_contents($file, serialize(['exp' => time() + 43200, 'val' => $sql]), LOCK_EX);
    }

    public static function getSql(string $idsql): string
    {
        return $idsql === '' ? '' : self::buscaIdSql($idsql);
    }

    /**
     * Corrige ORDER BY por alias quando o alias coincide com coluna da função pipelined TABLES,
     * causando ORA-00960 (ambiguous column naming).
     * Usa posição numérica em vez de nome de alias.
     */
    private static function fixarOrderBy(string $idsql, string $sql): string
    {
        // tampoBordado e tampoBordadoMesa: col 16=TECIDO, 17=ESPESSURA
        if (in_array($idsql, ['pcp.relatorioProd.tampoBordado', 'pcp.relatorioProd.tampoBordadoMesa'])) {
            return preg_replace(
                '/ORDER\s+BY\s+ESPESSURA\s+DESC\s*,\s*TECIDO\s+ASC/i',
                'ORDER BY 17 DESC, 16 ASC',
                $sql
            );
        }
        // tampoBordadoConj: col 13=TECIDO, 14=ESPESSURA
        if ($idsql === 'pcp.relatorioProd.tampoBordadoConj') {
            return preg_replace(
                '/ORDER\s+BY\s+ESPESSURA\s+DESC\s*,\s*TECIDO\s+ASC/i',
                'ORDER BY 14 DESC, 13 ASC',
                $sql
            );
        }
        return $sql;
    }

    public static function buscaIdSql(string $idsql): string
    {
        // 1. In-memory (mesma requisição)
        if (isset(self::$cache[$idsql])) {
            return self::$cache[$idsql];
        }

        // 2. Arquivo (entre requisições — 12h TTL)
        $fromFile = self::fileGet($idsql);
        if ($fromFile !== null) {
            $fixed = self::fixarOrderBy($idsql, $fromFile);
            self::$cache[$idsql] = $fixed;
            return $fixed;
        }

        // 3. Oracle (só na primeira vez ou após expirar)
        try {
            $pdo  = db::getInstance('focco');
            $stmt = $pdo->prepare("SELECT sql AS sql_texto FROM focco3i.gazin_sqls WHERE idsql = :idsql");
            $stmt->bindParam(':idsql', $idsql, \PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$resultado) {
                throw new \Exception("SQL não encontrado no Focco: {$idsql}");
            }

            $sqlEncontrado = is_resource($resultado['SQL_TEXTO'])
                ? stream_get_contents($resultado['SQL_TEXTO'])
                : ($resultado['SQL_TEXTO'] ?? '');

            if (empty($sqlEncontrado)) {
                throw new \Exception("SQL vazio para idsql: {$idsql}");
            }

            $sqlTrimmed = self::fixarOrderBy($idsql, trim($sqlEncontrado));
            self::$cache[$idsql] = $sqlTrimmed;
            self::fileSet($idsql, $sqlTrimmed);
            return $sqlTrimmed;

        } catch (\Exception $e) {
            throw new Exception("Falha ao chamar SQL do Focco: " . $e->getMessage(), 1);
        }
    }

    /** Invalida cache de um SQL específico (ex: após update no Oracle). */
    public static function invalidar(string $idsql): void
    {
        unset(self::$cache[$idsql]);
        $file = self::cacheDir() . DIRECTORY_SEPARATOR . md5($idsql) . '.sql';
        @unlink($file);
    }
}
