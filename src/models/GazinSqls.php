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

    /**
     * Registrar log de alteração de SQL
     * @param string $idsql
     * @param string|null $sqlAnterior
     * @param string $sqlNovo
     * @param string $usuario
     * @param string|null $observacao
     * @return bool
     */
    public static function registrarLog($idsql, $acao, $sqlAnterior, $sqlNovo, $usuario, $observacao = null)
    {
        $sqlAnteriorEscapado = $sqlAnterior ? "'" . str_replace("'", "''", $sqlAnterior) . "'" : 'NULL';
        $sqlNovoEscapado = $sqlNovo ? "'" . str_replace("'", "''", $sqlNovo) . "'" : 'NULL';
        $observacaoEscapada = $observacao ? "'" . str_replace("'", "''", $observacao) . "'" : 'NULL';
        
        $sql = "INSERT INTO FOCCO3I.TGAZIN_SQL_LOG (IDSQL, ACAO, SQL_ANTERIOR, SQL_NOVO, USUARIO, OBSERVACAO) 
                VALUES (
                    '" . str_replace("'", "''", $idsql) . "',
                    '" . str_replace("'", "''", $acao) . "',
                    " . $sqlAnteriorEscapado . ",
                    " . $sqlNovoEscapado . ",
                    '" . str_replace("'", "''", $usuario) . "',
                    " . $observacaoEscapada . "
                )";
        
        try {
            $result = Database::switchParams('focco', [], null, true, false, null, $sql);
            return !$result['error'];
        } catch (\Throwable $e) {
            // Log pode falhar se tabela não existir, não bloquear operação principal
            return false;
        }
    }

    /**
     * Listar histórico de alterações de um SQL
     * @param string $idsql
     * @return array
     */
    public static function listarHistorico($idsql)
    {
        $sql = "SELECT 
                    IDLOG,
                    IDSQL,
                    ACAO,
                    DBMS_LOB.SUBSTR(SQL_ANTERIOR, 4000, 1) AS SQL_ANTERIOR,
                    DBMS_LOB.SUBSTR(SQL_NOVO, 4000, 1) AS SQL_NOVO,
                    USUARIO,
                    TO_CHAR(DT_ALTERACAO, 'DD/MM/YYYY HH24:MI:SS') AS DATA_ALTERACAO,
                    OBSERVACAO
                FROM FOCCO3I.TGAZIN_SQL_LOG
                WHERE IDSQL = '" . str_replace("'", "''", $idsql) . "'
                ORDER BY DT_ALTERACAO DESC";
        
        try {
            $result = Database::switchParams('focco', [], null, true, false, null, $sql);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Validar sintaxe de um SQL (dry-run)
     * @param string $sql
     * @return array ['valido' => bool, 'erro' => string|null]
     */
    public static function validarSintaxe($sql)
    {
        try {
            // Executa com exec: false para apenas validar sintaxe
            $result = Database::switchParams('focco', [], null, false, false, null, $sql);
            return ['valido' => true, 'erro' => null];
        } catch (\Throwable $e) {
            return ['valido' => false, 'erro' => $e->getMessage()];
        }
    }
}
