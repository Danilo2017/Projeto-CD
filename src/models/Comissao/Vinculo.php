<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Vínculo de Funcionário com Centro de Trabalho e Recurso
 * Tabela: FOCCO3I.TGAZIN_VINC_FUNC
 */
class Vinculo
{
    // Tipos de vínculo
    const TIPO_NORMAL = 'N';
    const TIPO_APOIO = 'A';

    private static $colunaApoioCache = null;

    /**
     * Verifica se a coluna TIPO_VINCULO existe na tabela
     * Para compatibilidade retroativa (resultado cacheado)
     */
    public static function verificarColunaApoio()
    {
        if (self::$colunaApoioCache !== null) {
            return self::$colunaApoioCache;
        }
        $result = Database::switchParams('focco', [], 'comissao.vinculo.verificarColunaApoio', true);
        $row = $result['retorno'][0] ?? null;
        self::$colunaApoioCache = ($row['EXISTE'] ?? 0) > 0;
        return self::$colunaApoioCache;
    }

    /**
     * Listar vínculos com filtros opcionais
     */
    public static function listar($filtros = [])
    {
        $params = [
            'filtro_empr' => !empty($filtros['id_empr']) ? "AND v.ID_EMPR = " . intval($filtros['id_empr']) : '--',
            'filtro_centro' => !empty($filtros['id_centro_trab']) ? "AND v.ID_CENTRO_TRAB = " . intval($filtros['id_centro_trab']) : '--',
            'filtro_recurso' => !empty($filtros['id_recurso']) ? "AND v.ID_RECURSO = " . intval($filtros['id_recurso']) : '--',
            'filtro_func' => !empty($filtros['id_funcionario']) ? "AND v.ID_FUNCIONARIO = " . intval($filtros['id_funcionario']) : '--',
            'filtro_ativo' => isset($filtros['ativo']) ? "AND v.ATIVO = '" . ($filtros['ativo'] === 'S' ? 'S' : 'N') . "'" : '--',
        ];

        try {
            $result = Database::switchParams('focco', $params, 'comissao.vinculo.listar', true);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            // Fallback: SQL sem TIPO_VINCULO caso a coluna não exista
            $sqlFallback = "SELECT v.ID_VINCULO, v.ID_EMPR, v.ID_FUNCIONARIO, f.COD_FUNC, f.NOME AS FUNCIONARIO_NOME, "
                . "v.ID_CENTRO_TRAB, c.COD_CENTRO, c.DESCRICAO AS CENTRO_DESCRICAO, "
                . "v.ID_RECURSO, r.COD_MAQUINA, r.DESCRICAO AS RECURSO_DESCRICAO, v.ATIVO, "
                . "'N' AS TIPO_VINCULO "
                . "FROM FOCCO3I.TGAZIN_VINC_FUNC v "
                . "INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO "
                . "INNER JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = v.ID_CENTRO_TRAB "
                . "LEFT JOIN FOCCO3I.TMAQUINAS r ON r.ID = v.ID_RECURSO "
                . "WHERE 1=1 :filtro_empr :filtro_centro :filtro_recurso :filtro_func :filtro_ativo "
                . "ORDER BY c.DESCRICAO, f.NOME";
            $result = Database::switchParams('focco', $params, null, true, true, null, $sqlFallback);
            return $result['retorno'] ?? [];
        }
    }

    /**
     * Listar funcionários de apoio de um centro de trabalho
     */
    public static function listarApoioPorCentro($idCentroTrab, $idEmpr = null)
    {
        if (!self::verificarColunaApoio()) {
            return [];
        }

        $params = [
            'id_centro_trab' => intval($idCentroTrab),
            'filtro_empr' => $idEmpr ? "AND v.ID_EMPR = " . intval($idEmpr) : '--',
        ];

        $result = Database::switchParams('focco', $params, 'comissao.vinculo.listarApoioPorCentro', true);
        return $result['retorno'] ?? [];
    }

    /**
     * Inserir novo vínculo
     */
    public static function inserir($idEmpr, $idFuncionario, $idCentroTrab, $idRecurso = null, $tipoVinculo = 'N')
    {
        $params = [
            'id_empr' => intval($idEmpr),
            'id_funcionario' => intval($idFuncionario),
            'id_centro_trab' => intval($idCentroTrab),
            'id_recurso' => $idRecurso !== null ? intval($idRecurso) : 'NULL',
            'tipo_vinculo' => "'" . ($tipoVinculo === 'A' ? 'A' : 'N') . "'",
        ];

        $result = Database::switchParams('focco', $params, 'comissao.vinculo.inserir', true);
        return !$result['error'];
    }

    /**
     * Atualizar vínculo
     */
    public static function atualizar($id, $idCentroTrab, $idRecurso = null, $tipoVinculo = 'N')
    {
        $params = [
            'id' => intval($id),
            'id_centro_trab' => intval($idCentroTrab),
            'id_recurso' => $idRecurso !== null ? intval($idRecurso) : 'NULL',
            'tipo_vinculo' => "'" . ($tipoVinculo === 'A' ? 'A' : 'N') . "'",
        ];

        $result = Database::switchParams('focco', $params, 'comissao.vinculo.atualizar', true);
        return !$result['error'];
    }

    /**
     * Alterar status (ativar/inativar)
     */
    public static function alterarStatus($id, $ativo)
    {
        $params = [
            'id' => intval($id),
            'ativo' => "'" . ($ativo ? 'S' : 'N') . "'",
        ];

        $result = Database::switchParams('focco', $params, 'comissao.vinculo.alterarStatus', true);
        return !$result['error'];
    }

    /**
     * Excluir vínculo
     */
    public static function excluir($id)
    {
        $params = ['id' => intval($id)];

        $result = Database::switchParams('focco', $params, 'comissao.vinculo.excluir', true);
        return !$result['error'];
    }

    /**
     * Verificar se já existe vínculo
     */
    public static function existeVinculo($idFuncionario, $idCentroTrab, $idRecurso = null, $idEmpr = null)
    {
        $params = [
            'id_funcionario' => intval($idFuncionario),
            'id_centro_trab' => intval($idCentroTrab),
            'filtro_recurso_valor' => $idRecurso ? "AND ID_RECURSO = " . intval($idRecurso) : '--',
            'filtro_recurso_null' => !$idRecurso ? "AND ID_RECURSO IS NULL" : '--',
            'filtro_empr' => $idEmpr ? "AND ID_EMPR = " . intval($idEmpr) : '--',
        ];

        $result = Database::switchParams('focco', $params, 'comissao.vinculo.existeVinculo', true);
        $row = $result['retorno'][0] ?? null;
        return ($row['TOTAL'] ?? 0) > 0;
    }

    /**
     * Listar centros de trabalho que possuem vínculo cadastrado
     */
    public static function listarCentrosComVinculo($idEmpr = null)
    {
        $params = [
            'filtro_empr' => $idEmpr ? "AND v.ID_EMPR = " . intval($idEmpr) : '--',
        ];

        try {
            $result = Database::switchParams('focco', $params, 'comissao.vinculo.listarCentrosComVinculo', true);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            // Fallback: SQL simplificada sem joins extras (TEMP_CC/TCC)
            $sqlFallback = "SELECT DISTINCT c.ID, c.COD_CENTRO, c.DESCRICAO "
                . "FROM FOCCO3I.TGAZIN_VINC_FUNC v "
                . "INNER JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = v.ID_CENTRO_TRAB "
                . "WHERE v.ATIVO = 'S' :filtro_empr "
                . "ORDER BY c.COD_CENTRO, c.DESCRICAO";
            $result = Database::switchParams('focco', $params, null, true, true, null, $sqlFallback);
            return $result['retorno'] ?? [];
        }
    }

    /**
     * Listar recursos que possuem vínculo cadastrado
     */
    public static function listarRecursosComVinculo($idEmpr = null, $idCentroTrab = null)
    {
        $params = [
            'filtro_empr' => $idEmpr ? "AND v.ID_EMPR = " . intval($idEmpr) : '--',
            'filtro_centro' => $idCentroTrab ? "AND v.ID_CENTRO_TRAB = " . intval($idCentroTrab) : '--',
        ];

        try {
            $result = Database::switchParams('focco', $params, 'comissao.vinculo.listarRecursosComVinculo', true);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            // Fallback: SQL simplificada sem filtro TP_RECURSO
            $sqlFallback = "SELECT DISTINCT r.ID, r.COD_MAQUINA, r.DESCRICAO "
                . "FROM FOCCO3I.TGAZIN_VINC_FUNC v "
                . "INNER JOIN FOCCO3I.TMAQUINAS r ON r.ID = v.ID_RECURSO "
                . "WHERE v.ATIVO = 'S' AND v.ID_RECURSO IS NOT NULL :filtro_empr :filtro_centro "
                . "ORDER BY r.COD_MAQUINA, r.DESCRICAO";
            $result = Database::switchParams('focco', $params, null, true, true, null, $sqlFallback);
            return $result['retorno'] ?? [];
        }
    }

    /**
     * Listar funcionários que possuem vínculo cadastrado
     */
    public static function listarFuncionariosComVinculo($idEmpr = null, $busca = null)
    {
        $buscaSanitizada = $busca ? str_replace("'", "''", $busca) : null;
        $params = [
            'filtro_empr' => $idEmpr ? "AND v.ID_EMPR = " . intval($idEmpr) : '--',
            'filtro_busca' => $buscaSanitizada ? "AND (UPPER(f.NOME) LIKE UPPER('%" . $buscaSanitizada . "%') OR TO_CHAR(f.COD_FUNC) LIKE '%" . $buscaSanitizada . "%')" : '--',
        ];

        $result = Database::switchParams('focco', $params, 'comissao.vinculo.listarFuncionariosComVinculo', true);
        return $result['retorno'] ?? [];
    }
}
