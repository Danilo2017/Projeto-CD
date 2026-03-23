<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Regras Específicas de Comissão por Funcionário
 * Tabela: FOCCO3I.TGAZIN_REGRA_FUNC
 */
class RegraFuncionario
{
    const TIPO_VALOR_FIXO = 'V';
    const TIPO_PERCENTUAL = 'P';
    const TIPO_FIXO_TOTAL = 'F';
    const TIPO_MISTO = 'M';

    /**
     * Listar regras com filtros
     */
    public static function listar($filtros = [])
    {
        $params = [];
        $params['filtro_empr'] = !empty($filtros['id_empr']) ? "AND r.ID_EMPR = " . intval($filtros['id_empr']) : '--';
        $params['filtro_funcionario'] = !empty($filtros['id_funcionario']) ? "AND r.ID_FUNCIONARIO = " . intval($filtros['id_funcionario']) : '--';
        $params['filtro_centro'] = !empty($filtros['id_centro_trab']) ? "AND r.ID_CENTRO_TRAB = " . intval($filtros['id_centro_trab']) : '--';
        $params['filtro_status'] = (isset($filtros['status']) && $filtros['status'] !== '')
            ? "AND r.ATIVO = '" . str_replace("'", "''", $filtros['status']) . "'"
            : (isset($filtros['status']) && $filtros['status'] === '' ? '--' : "AND r.ATIVO = 'S'");

        $result = Database::switchParams('focco', $params, 'comissao.regra.listar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    /**
     * Buscar regra por ID
     */
    public static function buscarPorId($id)
    {
        $params = [];
        $params['id'] = intval($id);

        $result = Database::switchParams('focco', $params, 'comissao.regra.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    /**
     * Buscar regra ativa para funcionário em uma data específica
     */
    public static function buscarRegraAtiva($idFuncionario, $idCentroTrab = null, $data = null, $emprId = null)
    {
        if (!$data) {
            $data = date('Y-m-d');
        }

        // Se informado centro de trabalho, buscar regra específica primeiro
        if ($idCentroTrab) {
            $params = [];
            $params['id_funcionario'] = intval($idFuncionario);
            $params['data'] = "'" . str_replace("'", "''", $data) . "'";
            $params['data2'] = "'" . str_replace("'", "''", $data) . "'";
            $params['id_centro_trab'] = intval($idCentroTrab);
            $params['filtro_empr'] = $emprId ? "AND r.ID_EMPR = " . intval($emprId) : '--';

            $result = Database::switchParams('focco', $params, 'comissao.regra.buscarAtivaCentro', true);
            if (!$result['error'] && !empty($result['retorno'])) {
                return $result['retorno'][0];
            }
        }

        // Buscar regra geral (sem centro)
        $params = [];
        $params['id_funcionario'] = intval($idFuncionario);
        $params['data'] = "'" . str_replace("'", "''", $data) . "'";
        $params['data2'] = "'" . str_replace("'", "''", $data) . "'";
        $params['filtro_empr'] = $emprId ? "AND r.ID_EMPR = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.regra.buscarAtivaGeral', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    /**
     * Obter próximo ID (tenta sequência, depois MAX+1)
     */
    private static function proximoIdRegra()
    {
        // Tenta usar a sequência
        $result = Database::switchParams('focco', [], 'comissao.regra.nextval', true);
        if (!$result['error'] && !empty($result['retorno'])) {
            return $result['retorno'][0]['ID'] ?? null;
        }

        // Se não houver sequência, usar MAX + 1
        $result = Database::switchParams('focco', [], 'comissao.regra.maxId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0]['ID'] ?? 1;
    }

    /**
     * Inserir nova regra
     */
    public static function inserir($dados)
    {
        $novoId = self::proximoIdRegra();

        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        if ($dtVigenciaFim === '' || $dtVigenciaFim === '31/12/2009') {
            $dtVigenciaFim = null;
        }

        $params = [];
        $params['id'] = intval($novoId);
        $params['id_empr'] = intval($dados['id_empr']);
        $params['id_funcionario'] = intval($dados['id_funcionario']);
        $params['id_centro_trab'] = $dados['id_centro_trab'] ? intval($dados['id_centro_trab']) : 'NULL';
        $params['descricao'] = $dados['descricao'] ? "'" . str_replace("'", "''", $dados['descricao']) . "'" : 'NULL';
        $params['tipo_comissao'] = "'" . str_replace("'", "''", $dados['tipo_comissao']) . "'";
        $params['valor_comissao'] = floatval($dados['valor_comissao'] ?? 0);
        $params['valor_fixo'] = $dados['valor_fixo'] ? floatval($dados['valor_fixo']) : 'NULL';
        $params['dt_vigencia_ini'] = "'" . str_replace("'", "''", $dados['dt_vigencia_ini']) . "'";
        $params['dt_vigencia_fim'] = $dtVigenciaFim !== null ? "'" . str_replace("'", "''", $dtVigenciaFim) . "'" : 'NULL';
        $params['dt_vigencia_fim2'] = $params['dt_vigencia_fim'];
        $params['prioridade'] = intval($dados['prioridade'] ?? 1);
        $params['id_usuario'] = ($dados['id_usuario'] ?? null) !== null ? intval($dados['id_usuario']) : 'NULL';

        $result = Database::switchParams('focco', $params, 'comissao.regra.inserir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $novoId;
    }

    /**
     * Atualizar regra
     */
    public static function atualizar($id, $dados)
    {
        $params = [];
        $params['id'] = intval($id);
        $params['id_funcionario'] = intval($dados['id_funcionario']);
        $params['id_centro_trab'] = $dados['id_centro_trab'] ? intval($dados['id_centro_trab']) : 'NULL';
        $params['descricao'] = $dados['descricao'] ? "'" . str_replace("'", "''", $dados['descricao']) . "'" : 'NULL';
        $params['tipo_comissao'] = "'" . str_replace("'", "''", $dados['tipo_comissao']) . "'";
        $params['valor_comissao'] = floatval($dados['valor_comissao'] ?? 0);
        $params['valor_fixo'] = $dados['valor_fixo'] ? floatval($dados['valor_fixo']) : 'NULL';
        $params['dt_vigencia_ini'] = "'" . str_replace("'", "''", $dados['dt_vigencia_ini']) . "'";
        $dtFim = $dados['dt_vigencia_fim'] ?: null;
        $params['dt_vigencia_fim'] = $dtFim !== null ? "'" . str_replace("'", "''", $dtFim) . "'" : 'NULL';
        $params['prioridade'] = intval($dados['prioridade'] ?? 1);
        $params['id_usuario'] = ($dados['id_usuario'] ?? null) !== null ? intval($dados['id_usuario']) : 'NULL';

        $result = Database::switchParams('focco', $params, 'comissao.regra.atualizar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    /**
     * Inativar regra (soft delete)
     */
    public static function inativar($id, $idUsuario = null)
    {
        $params = [];
        $params['id'] = intval($id);
        $params['id_usuario'] = $idUsuario !== null ? intval($idUsuario) : 'NULL';

        $result = Database::switchParams('focco', $params, 'comissao.regra.inativar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    /**
     * Calcular comissão com base nos pontos e na regra específica
     */
    public static function calcularComissao($pontos, $regra, $valorFixoAdicional = null)
    {
        if (!$regra || !isset($regra['TIPO_COMISSAO']) || !isset($regra['VALOR_COMISSAO'])) {
            return 0;
        }

        $valor = floatval($regra['VALOR_COMISSAO']);
        $tipo = $regra['TIPO_COMISSAO'];
        $valorFixo = floatval($regra['VALOR_FIXO'] ?? $valorFixoAdicional ?? 0);

        switch ($tipo) {
            case self::TIPO_VALOR_FIXO:
                // Valor fixo por ponto/UP (multiplica pela quantidade de pontos)
                return $pontos * $valor;
            
            case self::TIPO_PERCENTUAL:
                // Percentual sobre os pontos (valor é percentual)
                return $pontos * ($valor / 100);
            
            case self::TIPO_FIXO_TOTAL:
                // Valor fixo total - NÃO depende da quantidade produzida
                return $valor;
            
            case self::TIPO_MISTO:
                // Valor fixo + valor por ponto
                // VALOR_COMISSAO = valor por ponto, VALOR_FIXO = valor fixo base
                return $valorFixo + ($pontos * $valor);
            
            default:
                // Tipo desconhecido, usar valor fixo por ponto
                return $pontos * $valor;
        }
    }

    /**
     * MÉTODO OTIMIZADO - Buscar regras ativas de MÚLTIPLOS funcionários em uma única query
     */
    public static function buscarRegraAtivaBatch(array $funcIds, ?int $centroTrabId = null, ?string $data = null, ?int $emprId = null): array
    {
        if (empty($funcIds)) {
            return [];
        }

        if (!$data) {
            $data = date('Y-m-d');
        }

        $params = [];
        $params['centro_order'] = intval($centroTrabId ?? 0);
        $params['in_func_ids'] = implode(',', array_map('intval', $funcIds));
        $params['data'] = "'" . str_replace("'", "''", $data) . "'";
        $params['data2'] = "'" . str_replace("'", "''", $data) . "'";
        $params['filtro_empr'] = $emprId ? "AND r.ID_EMPR = " . intval($emprId) : '--';

        $result = Database::switchParams('focco', $params, 'comissao.regra.buscarAtivaBatch', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Indexar por funcionário
        $regrasPorFunc = [];
        foreach ($funcIds as $funcId) {
            $regrasPorFunc[$funcId] = null;
        }

        foreach ($result['retorno'] as $regra) {
            $funcId = $regra['ID_FUNCIONARIO'];
            $regrasPorFunc[$funcId] = $regra;
        }

        return $regrasPorFunc;
    }
}
