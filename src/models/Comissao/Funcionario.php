<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para consulta de Funcionários no FOCCO
 * Tabela: FOCCO3I.TFUNCIONARIOS
 */
class Funcionario
{
    public static function listarAtivos($emprId = null, $busca = null)
    {
        $params = [
            'filtro_empr' => $emprId ? "AND F.EMPR_ID = " . intval($emprId) : '--',
            'filtro_busca' => $busca ? "AND (F.NOME LIKE '%" . str_replace("'", "''", $busca) . "%' OR F.COD_FUNC LIKE '%" . str_replace("'", "''", $busca) . "%')" : '--'
        ];
        $result = Database::switchParams('focco', $params, 'comissao.funcionario.listarAtivos', true);
        if (empty($result['error']) && !empty($result['retorno'])) {
            return $result['retorno'];
        }
        // Fallback: traz funcionários ativos diretamente da TFUNCIONARIOS
        $sqlFallback = "SELECT F.ID, F.COD_FUNC, F.NOME, F.EMPR_ID "
            . "FROM FOCCO3I.TFUNCIONARIOS F "
            . "WHERE F.SITUACAO = 'A' :filtro_empr :filtro_busca "
            . "ORDER BY F.NOME";
        $result = Database::switchParams('focco', $params, null, true, true, null, $sqlFallback);
        return $result['retorno'] ?? [];
    }

    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'comissao.funcionario.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function buscarPorCodigo($codFunc, $emprId)
    {
        $params = [
            'cod_func' => "'" . str_replace("'", "''", $codFunc) . "'",
            'empr_id' => intval($emprId)
        ];
        $result = Database::switchParams('focco', $params, 'comissao.funcionario.buscarPorCodigo', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function pesquisarPorNome($nome, $emprId = null)
    {
        $params = [
            'nome' => "'%" . str_replace("'", "''", $nome) . "%'",
            'filtro_empr' => $emprId ? "AND F.EMPR_ID = " . intval($emprId) : '--'
        ];
        $result = Database::switchParams('focco', $params, 'comissao.funcionario.pesquisarPorNome', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }
}
