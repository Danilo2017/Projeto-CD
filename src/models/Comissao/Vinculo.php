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
    private static $colunaCcCache = null;

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
     * Verifica se a coluna ID_CENTRO_ALOCACAO existe na tabela TGAZIN_VINC_FUNC.
     * Necessária para gravar a Alocação (Centro de Trabalho) por vínculo.
     */
    public static function verificarColunaCc()
    {
        if (self::$colunaCcCache !== null) {
            return self::$colunaCcCache;
        }
        $sql = "SELECT COUNT(*) AS EXISTE FROM ALL_TAB_COLUMNS "
            . "WHERE OWNER = 'FOCCO3I' AND TABLE_NAME = 'TGAZIN_VINC_FUNC' AND COLUMN_NAME = 'ID_EMP_CC'";
        $result = Database::switchParams('focco', [], null, true, false, null, $sql);
        $row = $result['retorno'][0] ?? null;
        self::$colunaCcCache = ($row['EXISTE'] ?? 0) > 0;
        return self::$colunaCcCache;
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

        // Alocação (Centro de Trabalho) vem APENAS do que estiver gravado em v.ID_CENTRO_ALOCACAO.
        // Vínculos ainda não alocados aparecem com a célula em branco para serem editados.
        $temColunaCc = self::verificarColunaCc();

        if ($temColunaCc) {
            // ID_EMP_CC armazena o ID de TCENTROS_TRAB (Alocação = Centro de Trabalho).
            $colunasCc = "v.ID_EMP_CC, ca.COD_CENTRO AS COD_CC, ca.DESCRICAO AS CC_DESCRICAO ";
            $joinsCc = "LEFT JOIN FOCCO3I.TCENTROS_TRAB ca ON ca.ID = v.ID_EMP_CC ";
        } else {
            $colunasCc = "NULL AS ID_EMP_CC, NULL AS COD_CC, NULL AS CC_DESCRICAO ";
            $joinsCc = "";
        }

        $sql = "SELECT v.ID_VINCULO, v.ID_EMPR, v.ID_FUNCIONARIO, f.COD_FUNC, f.NOME AS FUNCIONARIO_NOME, "
            . "v.ID_CENTRO_TRAB, c.COD_CENTRO, c.DESCRICAO AS CENTRO_DESCRICAO, "
            . "v.ID_RECURSO, r.COD_MAQUINA, r.DESCRICAO AS RECURSO_DESCRICAO, v.ATIVO, "
            . "NVL(v.TIPO_VINCULO, 'N') AS TIPO_VINCULO, "
            . $colunasCc
            . "FROM FOCCO3I.TGAZIN_VINC_FUNC v "
            . "INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = v.ID_FUNCIONARIO "
            . "INNER JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = v.ID_CENTRO_TRAB "
            . "LEFT JOIN FOCCO3I.TMAQUINAS r ON r.ID = v.ID_RECURSO "
            . $joinsCc
            . "WHERE 1=1 :filtro_empr :filtro_centro :filtro_recurso :filtro_func :filtro_ativo "
            . "ORDER BY c.DESCRICAO, f.NOME";
        $result = Database::switchParams('focco', $params, null, true, true, null, $sql);
        return $result['retorno'] ?? [];
    }

    /**
     * Listar Centros de Trabalho da empresa para popular o select de Alocação.
     * (Mantém o nome legado da função por compatibilidade.)
     * Não influencia cálculos.
     */
    public static function listarCentrosCusto($idEmpr = null)
    {
        $params = [
            'filtro_empr' => $idEmpr ? "AND tt.EMPR_ID = " . intval($idEmpr) : '--',
        ];
        // Retorna campos com aliases COD/DESCRICAO para o JS continuar funcionando.
        $sql = "SELECT tt.ID, tt.EMPR_ID, tt.COD_CENTRO AS COD, tt.DESCRICAO "
            . "FROM FOCCO3I.TCENTROS_TRAB tt "
            . "WHERE 1=1 :filtro_empr "
            . "ORDER BY tt.COD_CENTRO";
        $result = Database::switchParams('focco', $params, null, true, true, null, $sql);
        return $result['retorno'] ?? [];
    }

    /**
     * Retorna mapa de Alocação (Centro de Trabalho) por ID_FUNCIONARIO para uma empresa.
     * Resultado: [ funcId => ['ID_EMP_CC' => x, 'COD_CC' => 'xxx', 'CC_DESCRICAO' => 'yyy'] ]
     * (Aliases mantidos por compatibilidade.)
     * Usado nos relatórios para exibir a Alocação sem alterar cálculos.
     */
    public static function getAlocacaoPorFuncionario($idEmpr = null)
    {
        if (!self::verificarColunaCc()) {
            return [];
        }
        $params = [
            'filtro_empr' => $idEmpr ? "AND v.ID_EMPR = " . intval($idEmpr) : '--',
        ];
        $sql = "SELECT v.ID_FUNCIONARIO, v.ID_EMP_CC, ca.COD_CENTRO AS COD_CC, ca.DESCRICAO AS CC_DESCRICAO "
            . "FROM FOCCO3I.TGAZIN_VINC_FUNC v "
            . "LEFT JOIN FOCCO3I.TCENTROS_TRAB ca ON ca.ID = v.ID_EMP_CC "
            . "WHERE v.ATIVO = 'S' AND v.ID_EMP_CC IS NOT NULL :filtro_empr";
        $result = Database::switchParams('focco', $params, null, true, false, null, $sql);
        if (!empty($result['error'])) {
            return [];
        }
        $mapa = [];
        foreach (($result['retorno'] ?? []) as $row) {
            $funcId = $row['ID_FUNCIONARIO'] ?? null;
            if ($funcId !== null && !isset($mapa[$funcId])) {
                $mapa[$funcId] = [
                    'ID_EMP_CC' => $row['ID_EMP_CC'] ?? null,
                    'COD_CC' => $row['COD_CC'] ?? null,
                    'CC_DESCRICAO' => $row['CC_DESCRICAO'] ?? null,
                ];
            }
        }
        return $mapa;
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
    public static function inserir($idEmpr, $idFuncionario, $idCentroTrab, $idRecurso = null, $tipoVinculo = 'N', $idEmpCc = null)
    {
        $params = [
            'id_empr' => intval($idEmpr),
            'id_funcionario' => intval($idFuncionario),
            'id_centro_trab' => intval($idCentroTrab),
            'id_recurso' => $idRecurso !== null ? intval($idRecurso) : 'NULL',
            'tipo_vinculo' => "'" . ($tipoVinculo === 'A' ? 'A' : 'N') . "'",
            'id_emp_cc' => $idEmpCc !== null && $idEmpCc !== '' ? intval($idEmpCc) : 'NULL',
        ];

        // Tenta inserir com ID_EMP_CC; se a coluna não existir, faz fallback sem ela.
        $sqlComCc = "INSERT INTO FOCCO3I.TGAZIN_VINC_FUNC "
            . "(ID_VINCULO, ID_EMPR, ID_FUNCIONARIO, ID_CENTRO_TRAB, ID_RECURSO, TIPO_VINCULO, ID_EMP_CC, ATIVO, DT_CADASTRO) "
            . "VALUES (FOCCO3I.SEQ_TGAZIN_VINC_FUNC.NEXTVAL, :id_empr, :id_funcionario, :id_centro_trab, :id_recurso, :tipo_vinculo, :id_emp_cc, 'S', SYSDATE)";
        $result = Database::switchParams('focco', $params, null, true, true, null, $sqlComCc);
        if (empty($result['error'])) {
            self::$colunaCcCache = true;
            return true;
        }
        // Fallback apenas se a falha for por coluna inválida (ORA-00904 / ORA-06550 / PLS-00302)
        if (self::erroColunaInvalida($result['error'])) {
            self::$colunaCcCache = false;
            $sqlSemCc = "INSERT INTO FOCCO3I.TGAZIN_VINC_FUNC "
                . "(ID_VINCULO, ID_EMPR, ID_FUNCIONARIO, ID_CENTRO_TRAB, ID_RECURSO, TIPO_VINCULO, ATIVO, DT_CADASTRO) "
                . "VALUES (FOCCO3I.SEQ_TGAZIN_VINC_FUNC.NEXTVAL, :id_empr, :id_funcionario, :id_centro_trab, :id_recurso, :tipo_vinculo, 'S', SYSDATE)";
            $resultFallback = Database::switchParams('focco', $params, null, true, true, null, $sqlSemCc);
            if (empty($resultFallback['error'])) {
                return true;
            }
            throw new \Exception('Erro Oracle (sem ID_EMP_CC): ' . $resultFallback['error']);
        }
        throw new \Exception('Erro Oracle: ' . $result['error']);
    }

    /**
     * Atualizar vínculo
     */
    public static function atualizar($id, $idCentroTrab, $idRecurso = null, $tipoVinculo = 'N', $idEmpCc = null)
    {
        $params = [
            'id' => intval($id),
            'id_centro_trab' => intval($idCentroTrab),
            'id_recurso' => $idRecurso !== null ? intval($idRecurso) : 'NULL',
            'tipo_vinculo' => "'" . ($tipoVinculo === 'A' ? 'A' : 'N') . "'",
            'id_emp_cc' => $idEmpCc !== null && $idEmpCc !== '' ? intval($idEmpCc) : 'NULL',
        ];

        $sqlComCc = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC "
            . "SET ID_CENTRO_TRAB = :id_centro_trab, "
            . "    ID_RECURSO = :id_recurso, "
            . "    TIPO_VINCULO = :tipo_vinculo, "
            . "    ID_EMP_CC = :id_emp_cc "
            . "WHERE ID_VINCULO = :id";
        $result = Database::switchParams('focco', $params, null, true, true, null, $sqlComCc);
        if (empty($result['error'])) {
            self::$colunaCcCache = true;
            return true;
        }
        if (self::erroColunaInvalida($result['error'])) {
            self::$colunaCcCache = false;
            $sqlSemCc = "UPDATE FOCCO3I.TGAZIN_VINC_FUNC "
                . "SET ID_CENTRO_TRAB = :id_centro_trab, "
                . "    ID_RECURSO = :id_recurso, "
                . "    TIPO_VINCULO = :tipo_vinculo "
                . "WHERE ID_VINCULO = :id";
            $resultFallback = Database::switchParams('focco', $params, null, true, true, null, $sqlSemCc);
            if (empty($resultFallback['error'])) {
                return true;
            }
            throw new \Exception('Erro Oracle (sem ID_EMP_CC): ' . $resultFallback['error']);
        }
        throw new \Exception('Erro Oracle: ' . $result['error']);
    }

    /**
     * Detecta se uma mensagem de erro Oracle indica coluna inexistente.
     */
    private static function erroColunaInvalida($msg)
    {
        if (!$msg) return false;
        return (strpos($msg, 'ORA-00904') !== false)
            || (stripos($msg, 'invalid identifier') !== false)
            || (stripos($msg, 'identificador inv') !== false);
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
