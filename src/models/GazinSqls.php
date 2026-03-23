<?php

namespace src\models;

use core\Database;

class GazinSqls
{
    /**
     * Listar todos os SQLs cadastrados
     */
    public static function listar($busca = null)
    {
        $buscaSanitizada = $busca ? str_replace("'", "''", $busca) : null;
        $params = [
            'filtro_busca' => $buscaSanitizada
                ? "AND (UPPER(IDSQL) LIKE UPPER('%" . $buscaSanitizada . "%') OR UPPER(DBMS_LOB.SUBSTR(SQL, 4000, 1)) LIKE UPPER('%" . $buscaSanitizada . "%'))"
                : '--',
        ];

        $result = Database::switchParams('focco', $params, 'admin.sqls.listar', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Buscar SQL por idsql
     */
    public static function buscarPorId($idsql)
    {
        $params = [
            'idsql' => "'" . str_replace("'", "''", $idsql) . "'",
        ];

        $result = Database::switchParams('focco', $params, 'admin.sqls.buscarPorId', true);
        $rows = $result['retorno'] ?? [];
        return $rows[0] ?? null;
    }

    /**
     * Atualizar SQL existente
     */
    public static function atualizar($idsql, $sql)
    {
        $params = [
            'novo_sql' => "'" . str_replace("'", "''", $sql) . "'",
            'idsql' => "'" . str_replace("'", "''", $idsql) . "'",
        ];

        $result = Database::switchParams('focco', $params, 'admin.sqls.atualizar', true);
        return !$result['error'];
    }

    /**
     * Inserir novo SQL
     */
    public static function inserir($idsql, $sql)
    {
        $params = [
            'idsql' => "'" . str_replace("'", "''", $idsql) . "'",
            'novo_sql' => "'" . str_replace("'", "''", $sql) . "'",
        ];

        $result = Database::switchParams('focco', $params, 'admin.sqls.inserir', true);
        return !$result['error'];
    }

    /**
     * Excluir SQL
     */
    public static function excluir($idsql)
    {
        $params = [
            'idsql' => "'" . str_replace("'", "''", $idsql) . "'",
        ];

        $result = Database::switchParams('focco', $params, 'admin.sqls.excluir', true);
        return !$result['error'];
    }
}
