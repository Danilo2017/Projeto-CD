<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Gestão de Faltas de Funcionários
 * Tabela: FOCCO3I.TGAZIN_FALTA_FUNC
 */
class FaltaFuncionario
{
    const TIPO_INTEGRAL = 'I';
    const TIPO_PARCIAL = 'P';

    /**
     * Registrar falta de funcionário
     */
    public static function registrar($dados)
    {
        // Validar se já existe falta para o mesmo dia
        if (self::verificarFaltaExistente($dados['id_funcionario'], $dados['dt_falta'], $dados['id_empr'])) {
            throw new \Exception('Já existe uma falta registrada para este funcionário nesta data');
        }

        $tipoFalta = $dados['tipo_falta'] ?? self::TIPO_INTEGRAL;

        $params = [];
        $params['id_empr'] = intval($dados['id_empr']);
        $params['id_funcionario'] = intval($dados['id_funcionario']);
        $params['dt_falta'] = "'" . str_replace("'", "''", $dados['dt_falta']) . "'";
        $params['motivo'] = "'" . str_replace("'", "''", $dados['motivo']) . "'";
        $params['tipo_falta'] = "'" . str_replace("'", "''", $tipoFalta) . "'";
        $params['id_usuario'] = intval($dados['id_usuario']);

        $result = Database::switchParams('focco', $params, 'comissao.falta.inserir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Buscar o ID inserido
        $paramsId = [];
        $paramsId['id_funcionario'] = intval($dados['id_funcionario']);
        $paramsId['dt_falta'] = "'" . str_replace("'", "''", $dados['dt_falta']) . "'";
        $paramsId['id_empr'] = intval($dados['id_empr']);

        $resultId = Database::switchParams('focco', $paramsId, 'comissao.falta.buscarMaxId', true);
        $id = $resultId['retorno'][0]['ID'] ?? null;

        // Registrar log de auditoria
        self::registrarLog('TGAZIN_FALTA_FUNC', $id, 'I', null, $dados, $dados['id_usuario']);

        return $id;
    }

    /**
     * Verificar se já existe falta registrada para o funcionário na data
     */
    public static function verificarFaltaExistente($funcId, $data, $emprId)
    {
        $params = [];
        $params['func_id'] = intval($funcId);
        $params['dt_falta'] = "'" . str_replace("'", "''", $data) . "'";
        $params['empr_id'] = intval($emprId);

        $result = Database::switchParams('focco', $params, 'comissao.falta.verificarExistente', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return (int)($result['retorno'][0]['TOTAL'] ?? 0) > 0;
    }

    /**
     * Verificar faltas de um funcionário em um período
     */
    public static function verificarFaltasPeriodo($funcId, $dataIni, $dataFim, $emprId = null)
    {
        $params = [];
        $params['func_id'] = intval($funcId);
        $params['dt_ini'] = "'" . str_replace("'", "''", $dataIni) . "'";
        $params['dt_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND ID_EMPR = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.falta.verificarPeriodo', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    /**
     * Listar faltas com filtros
     */
    public static function listar($filtros = [])
    {
        $params = [];
        $params['filtro_empr'] = !empty($filtros['id_empr']) ? "AND FF.ID_EMPR = " . intval($filtros['id_empr']) : '--';
        $params['filtro_funcionario'] = !empty($filtros['id_funcionario']) ? "AND FF.ID_FUNCIONARIO = " . intval($filtros['id_funcionario']) : '--';
        $params['filtro_dt_inicio'] = !empty($filtros['dt_inicio']) ? "AND FF.DT_FALTA >= TO_DATE('" . str_replace("'", "''", $filtros['dt_inicio']) . "', 'YYYY-MM-DD')" : '--';
        $params['filtro_dt_fim'] = !empty($filtros['dt_fim']) ? "AND FF.DT_FALTA <= TO_DATE('" . str_replace("'", "''", $filtros['dt_fim']) . "', 'YYYY-MM-DD')" : '--';

        $result = Database::switchParams('focco', $params, 'comissao.falta.listar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    /**
     * Buscar falta por ID
     */
    public static function buscarPorId($id)
    {
        $params = [];
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.falta.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    /**
     * Atualizar falta
     */
    public static function atualizar($id, $dados)
    {
        // Buscar dados anteriores para auditoria
        $dadosAnteriores = self::buscarPorId($id);

        $tipoFalta = $dados['tipo_falta'] ?? self::TIPO_INTEGRAL;

        $params = [];
        $params['dt_falta'] = "'" . str_replace("'", "''", $dados['dt_falta']) . "'";
        $params['motivo'] = "'" . str_replace("'", "''", $dados['motivo']) . "'";
        $params['tipo_falta'] = "'" . str_replace("'", "''", $tipoFalta) . "'";
        $params['id_usuario'] = intval($dados['id_usuario']);
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.falta.atualizar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Registrar log de auditoria
        self::registrarLog('TGAZIN_FALTA_FUNC', $id, 'U', $dadosAnteriores, $dados, $dados['id_usuario']);

        return true;
    }

    /**
     * Excluir falta (exclusão física)
     */
    public static function excluir($id, $usuId)
    {
        // Buscar dados anteriores para auditoria
        $dadosAnteriores = self::buscarPorId($id);
        if (!$dadosAnteriores) {
            throw new \Exception('Falta não encontrada para exclusão');
        }

        $params = [];
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.falta.excluir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Registrar log de auditoria
        self::registrarLog('TGAZIN_FALTA_FUNC', $id, 'D', $dadosAnteriores, null, $usuId);

        return true;
    }

    /**
     * Obter datas com falta para um array de funcionários em um período
     */
    public static function obterFaltasPorFuncionarios($funcIds, $dataIni, $dataFim, $emprId = null)
    {
        if (empty($funcIds)) {
            return [];
        }

        $params = [];
        $params['in_func_ids'] = implode(',', array_map('intval', $funcIds));
        $params['dt_ini'] = "'" . str_replace("'", "''", $dataIni) . "'";
        $params['dt_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND ID_EMPR = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.falta.obterPorFuncionarios', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Organizar por funcionário
        $resultado = [];
        foreach ($result['retorno'] as $row) {
            $funcId = $row['ID_FUNCIONARIO'];
            if (!isset($resultado[$funcId])) {
                $resultado[$funcId] = [];
            }
            $resultado[$funcId][] = $row['DT_FALTA'];
        }

        return $resultado;
    }

    /**
     * Registrar log de auditoria
     */
    private static function registrarLog($tabela, $idRegistro, $operacao, $dadosAnteriores, $dadosNovos, $usuId)
    {
        try {
            $emprId = $_SESSION['empresa']['id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $dadosAntJson = $dadosAnteriores ? json_encode($dadosAnteriores) : null;
            $dadosNovJson = $dadosNovos ? json_encode($dadosNovos) : null;

            $params = [];
            $params['id_empr'] = $emprId !== null ? intval($emprId) : 'NULL';
            $params['tabela'] = "'" . str_replace("'", "''", $tabela) . "'";
            $params['id_registro'] = intval($idRegistro);
            $params['operacao'] = "'" . str_replace("'", "''", $operacao) . "'";
            $params['dados_anteriores'] = $dadosAntJson !== null ? "'" . str_replace("'", "''", $dadosAntJson) . "'" : 'NULL';
            $params['dados_novos'] = $dadosNovJson !== null ? "'" . str_replace("'", "''", $dadosNovJson) . "'" : 'NULL';
            $params['id_usuario'] = intval($usuId);
            $params['ip_usuario'] = $ip !== null ? "'" . str_replace("'", "''", $ip) . "'" : 'NULL';

            Database::switchParams('focco', $params, 'util.auditoria.inserir', true);
        } catch (\Exception $e) {
            // Log de erro silenciado
        }
    }

    /**
     * MÉTODO OTIMIZADO - Verificar faltas de MÚLTIPLOS funcionários em um período
     */
    public static function verificarFaltasPeriodoBatch(array $funcIds, string $dataIni, string $dataFim, ?int $emprId = null): array
    {
        if (empty($funcIds)) {
            return [];
        }

        $params = [];
        $params['in_func_ids'] = implode(',', array_map('intval', $funcIds));
        $params['dt_ini'] = "'" . str_replace("'", "''", $dataIni) . "'";
        $params['dt_fim'] = "'" . str_replace("'", "''", $dataFim) . "'";
        $params['filtro_empr'] = $emprId ? "AND ID_EMPR = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.falta.verificarPeriodoBatch', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Indexar por funcionário para acesso rápido O(1)
        $faltasPorFunc = [];
        foreach ($funcIds as $funcId) {
            $faltasPorFunc[$funcId] = [];
        }

        foreach ($result['retorno'] as $falta) {
            $funcId = $falta['ID_FUNCIONARIO'];
            $faltasPorFunc[$funcId][] = $falta;
        }

        return $faltasPorFunc;
    }
}


