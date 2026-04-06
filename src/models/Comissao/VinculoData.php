<?php

namespace src\models\Comissao;

use core\Database;

/**
 * Model para Vínculo de Funcionário por Data Específica
 * Permite que um funcionário atue como APOIO em dias específicos,
 * mesmo sendo NORMAL em seu vínculo principal
 * 
 * Tabela: FOCCO3I.TGAZIN_VINC_FUNC_DATA
 */
class VinculoData
{
    /**
     * Verificar se a tabela de datas existe
     * @idsql vinculodata.tabela.existe
     */
    public static function verificarTabelaExiste(): bool
    {
        $result = Database::switchParams('focco', [], 'vinculodata.tabela.existe', true);
        return ($result['retorno'][0]['EXISTE'] ?? 0) > 0;
    }

    /**
     * Verificar se coluna TIPO_CALCULO existe na tabela
     * @idsql vinculodata.coluna.tipoCalculoExiste
     */
    private static function colunaExiste(): bool
    {
        static $existe = null;
        if ($existe === null) {
            try {
                $result = Database::switchParams('focco', [], 'vinculodata.coluna.tipoCalculoExiste', true);
                $existe = ($result['retorno'][0]['EXISTE'] ?? 0) > 0;
            } catch (\Throwable $e) {
                $existe = false;
            }
        }
        return $existe;
    }

    /**
     * Listar datas de apoio de um vínculo
     * @idsql vinculodata.vinculo.listarPorVinculo
     * @param int $idVinculo ID do vínculo principal
     * @return array
     */
    public static function listarPorVinculo(int $idVinculo): array
    {
        $campoTipoCalculo = self::colunaExiste() ? "NVL(vd.TIPO_CALCULO, 'T') AS TIPO_CALCULO" : "'T' AS TIPO_CALCULO";
        
        $params = [
            'id_vinculo' => intval($idVinculo),
            'campo_tipo_calculo' => $campoTipoCalculo
        ];
        
        try {
            $result = Database::switchParams('focco', $params, 'vinculodata.vinculo.listarPorVinculo', true);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Listar datas de apoio de um funcionário em um mês específico
     * @param int $idFuncionario
     * @param int $idEmpr
     * @param string $mesAno Formato: 'YYYY-MM'
     * @idsql vinculodata.funcionario.listarPorMes
     * @return array
     */
    public static function listarPorFuncionarioMes(int $idFuncionario, int $idEmpr, string $mesAno): array
    {
        $params = [
            'id_funcionario' => intval($idFuncionario),
            'id_empr' => intval($idEmpr),
            'mes_ano' => "'" . $mesAno . "'"
        ];
        
        try {
            $result = Database::switchParams('focco', $params, 'vinculodata.funcionario.listarPorMes', true);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verificar se um funcionário atua como apoio em uma data específica
     * Retorna o centro de trabalho onde ele atua como apoio, ou null se não atua
     * 
     * @param int $idFuncionario
     * @param int $idEmpr
     * @param string $data Formato: 'YYYY-MM-DD'
     * @idsql vinculodata.apoio.verificarNaData
     * @return array|null
     */
    public static function verificarApoioNaData(int $idFuncionario, int $idEmpr, string $data): ?array
    {
        $params = [
            'id_funcionario' => intval($idFuncionario),
            'id_empr' => intval($idEmpr),
            'data' => "'" . $data . "'"
        ];
        
        try {
            $result = Database::switchParams('focco', $params, 'vinculodata.apoio.verificarNaData', true);
            return $result['retorno'][0] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Inserir nova data de apoio
     * @param int $idVinculo ID do vínculo principal
     * @param string $data Formato: 'YYYY-MM-DD'
     * @param int|null $idCentroTrabApoio Centro onde vai atuar como apoio (se null, usa o mesmo do vínculo)
     * @param string $tipoCalculo Tipo de cálculo: 'T' = Total, 'M' = Média
     * @return bool
     */
    /**
     * Inserir nova data de apoio
     * @idsql vinculodata.vinculo.buscarCentro, vinculodata.registro.buscarExistente, vinculodata.registro.reativarComTipo, vinculodata.registro.inserirComTipo
     * @param int $idVinculo ID do vínculo principal
     * @param string $data Formato: 'YYYY-MM-DD'
     * @param int|null $idCentroTrabApoio Centro onde vai atuar como apoio (se null, usa o mesmo do vínculo)
     * @param string $tipoCalculo Tipo de cálculo: 'T' = Total, 'M' = Média
     * @return bool
     */
    public static function inserir(int $idVinculo, string $data, ?int $idCentroTrabApoio = null, string $tipoCalculo = 'T'): bool
    {
        // Se não especificou centro, busca do vínculo principal
        if ($idCentroTrabApoio === null) {
            $params = ['id_vinculo' => intval($idVinculo)];
            $resultVinculo = Database::switchParams('focco', $params, 'vinculodata.vinculo.buscarCentro', true);
            $idCentroTrabApoio = $resultVinculo['retorno'][0]['ID_CENTRO_TRAB'] ?? null;
        }
        
        $centroValue = $idCentroTrabApoio !== null ? intval($idCentroTrabApoio) : 'NULL';
        
        // Validar tipo de cálculo (T = Total, M = Média)
        $tipoCalculo = in_array(strtoupper($tipoCalculo), ['T', 'M']) ? strtoupper($tipoCalculo) : 'T';
        
        // Verificar se já existe registro (ativo ou inativo) para esta data
        $paramsExiste = [
            'id_vinculo' => intval($idVinculo),
            'data' => "'" . $data . "'"
        ];
        
        $resultExiste = Database::switchParams('focco', $paramsExiste, 'vinculodata.registro.buscarExistente', true);
        $registroExistente = $resultExiste['retorno'][0] ?? null;
        
        if ($registroExistente) {
            // Se existe registro inativo, reativa e atualiza
            $paramsUpdate = [
                'centro_value' => $centroValue,
                'id_vinculo_data' => intval($registroExistente['ID_VINCULO_DATA'])
            ];
            
            if (self::colunaExiste()) {
                $paramsUpdate['tipo_calculo'] = "'" . $tipoCalculo . "'";
                $result = Database::switchParams('focco', $paramsUpdate, 'vinculodata.registro.reativarComTipo', true);
            } else {
                $result = Database::switchParams('focco', $paramsUpdate, 'vinculodata.registro.reativarSemTipo', true);
            }
            
            return !$result['error'];
        }
        
        // Não existe, insere novo registro
        $paramsInsert = [
            'id_vinculo' => intval($idVinculo),
            'data' => "'" . $data . "'",
            'centro_value' => $centroValue
        ];
        
        if (self::colunaExiste()) {
            $paramsInsert['tipo_calculo'] = "'" . $tipoCalculo . "'";
            $result = Database::switchParams('focco', $paramsInsert, 'vinculodata.registro.inserirComTipo', true);
        } else {
            $result = Database::switchParams('focco', $paramsInsert, 'vinculodata.registro.inserirSemTipo', true);
        }
        
        return !$result['error'];
    }

    /**
     * Inserir múltiplas datas de apoio de uma vez
     * @param int $idVinculo
     * @param array $datas Array de datas no formato 'YYYY-MM-DD'
     * @param int|null $idCentroTrabApoio
     * @param string $tipoCalculo Tipo de cálculo: 'T' = Total, 'M' = Média
     * @return int Quantidade de datas inseridas/atualizadas
     */
    public static function inserirMultiplas(int $idVinculo, array $datas, ?int $idCentroTrabApoio = null, string $tipoCalculo = 'T'): int
    {
        $count = 0;
        foreach ($datas as $data) {
            // A função inserir agora lida com reativação de registros inativos
            if (self::inserir($idVinculo, $data, $idCentroTrabApoio, $tipoCalculo)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Verificar se já existe registro para a data
     * @idsql vinculodata.data.existe
     */
    public static function existeData(int $idVinculo, string $data): bool
    {
        $params = [
            'id_vinculo' => intval($idVinculo),
            'data' => "'" . $data . "'"
        ];
        
        $result = Database::switchParams('focco', $params, 'vinculodata.data.existe', true);
        return ($result['retorno'][0]['EXISTE'] ?? 0) > 0;
    }

    /**
     * Excluir data de apoio (inativa o registro)
     * @idsql vinculodata.registro.excluirPorId
     */
    public static function excluir(int $idVinculoData): bool
    {
        $params = ['id_vinculo_data' => intval($idVinculoData)];
        
        $result = Database::switchParams('focco', $params, 'vinculodata.registro.excluirPorId', true);
        return !$result['error'];
    }

    /**
     * Excluir data de apoio por vínculo e data
     * @idsql vinculodata.registro.excluirPorData
     */
    public static function excluirPorData(int $idVinculo, string $data): bool
    {
        $params = [
            'id_vinculo' => intval($idVinculo),
            'data' => "'" . $data . "'"
        ];
        
        $result = Database::switchParams('focco', $params, 'vinculodata.registro.excluirPorData', true);
        return !$result['error'];
    }

    /**
     * Excluir todas as datas de um vínculo
     * @idsql vinculodata.vinculo.excluirTodas
     */
    public static function excluirTodasPorVinculo(int $idVinculo): bool
    {
        $params = ['id_vinculo' => intval($idVinculo)];
        
        $result = Database::switchParams('focco', $params, 'vinculodata.vinculo.excluirTodas', true);
        return !$result['error'];
    }

    /**
     * Atualizar datas de apoio (remove antigas e adiciona novas)
     * @param int $idVinculo
     * @param array $datas Array de datas no formato 'YYYY-MM-DD'
     * @param int|null $idCentroTrabApoio
     * @return bool
     */
    public static function atualizarDatas(int $idVinculo, array $datas, ?int $idCentroTrabApoio = null): bool
    {
        // Primeiro, inativa todas as datas existentes
        self::excluirTodasPorVinculo($idVinculo);
        
        // Depois, insere as novas
        foreach ($datas as $data) {
            self::inserir($idVinculo, $data, $idCentroTrabApoio);
        }
        
        return true;
    }

    /**
     * Buscar datas de apoio em batch para múltiplos funcionários em um período
     * @idsql vinculodata.apoio.buscarDatasApoioBatch
     * @param array $funcIds IDs dos funcionários
     * @param int $emprId ID da empresa
     * @param string $periodoIni Data início (YYYY-MM-DD)
     * @param string $periodoFim Data fim (YYYY-MM-DD)
     * @return array Indexado por ID_FUNCIONARIO -> [DATA => ['centro' => id, 'tipo_calculo' => 'T'|'M']]
     */
    public static function buscarDatasApoioBatch(array $funcIds, int $emprId, string $periodoIni, string $periodoFim): array
    {
        if (empty($funcIds)) {
            return [];
        }

        $inIds = implode(',', array_map('intval', $funcIds));
        $campoTipoCalculo = self::colunaExiste() ? "NVL(vd.TIPO_CALCULO, 'T') AS TIPO_CALCULO" : "'T' AS TIPO_CALCULO";
        
        $params = [
            'func_ids' => $inIds,
            'id_empr' => intval($emprId),
            'periodo_ini' => "'" . $periodoIni . "'",
            'periodo_fim' => "'" . $periodoFim . "'",
            'campo_tipo_calculo' => $campoTipoCalculo
        ];
        
        try {
            $result = Database::switchParams('focco', $params, 'vinculodata.apoio.buscarDatasApoioBatch', true);
            $rows = $result['retorno'] ?? [];
            
            // Indexar por funcionário e data - agora retorna objeto com centro e tipo_calculo
            $datasApoio = [];
            foreach ($rows as $row) {
                $funcId = (int)$row['ID_FUNCIONARIO'];
                $data = $row['DATA'];
                $centroApoio = $row['ID_CENTRO_TRAB_APOIO'] ?? $row['CENTRO_PRINCIPAL'];
                $tipoCalculo = $row['TIPO_CALCULO'] ?? 'T';
                
                if (!isset($datasApoio[$funcId])) {
                    $datasApoio[$funcId] = [];
                }
                $datasApoio[$funcId][$data] = [
                    'centro' => $centroApoio,
                    'tipo_calculo' => $tipoCalculo
                ];
            }
            
            return $datasApoio;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Buscar todos os funcionários com datas de apoio configuradas em um período
     * Retorna dados básicos dos funcionários para incluir no relatório mesmo sem apontamentos
     * @idsql vinculodata.apoio.buscarFuncionariosPeriodo
     * @param int $emprId
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int|null $centroTrabId Filtrar por centro de trabalho (principal ou apoio)
     * @return array Lista de funcionários com suas datas de apoio
     */
    public static function buscarFuncionariosComApoioPeriodo(int $emprId, string $periodoIni, string $periodoFim, ?int $centroTrabId = null): array
    {
        $filtroCtId = $centroTrabId 
            ? "AND (v.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR vd.ID_CENTRO_TRAB_APOIO = " . intval($centroTrabId) . ")"
            : '';
        
        $params = [
            'id_empr' => intval($emprId),
            'periodo_ini' => "'" . $periodoIni . "'",
            'periodo_fim' => "'" . $periodoFim . "'",
            'filtro_centro' => $filtroCtId
        ];
        
        try {
            $result = Database::switchParams('focco', $params, 'vinculodata.apoio.buscarFuncionariosPeriodo', true);
            return $result['retorno'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Buscar datas de apoio para todos os funcionários de um centro/empresa em um período
     * Usado para incluir funcionários que trabalham como apoio mas podem não ter apontamentos individuais
     * @idsql vinculodata.apoio.buscarTodasDatasPeriodo
     * @param int $emprId
     * @param string $periodoIni
     * @param string $periodoFim
     * @param int|null $centroTrabId
     * @return array Indexado por ID_FUNCIONARIO -> [DATA => ['centro' => id, 'tipo_calculo' => 'T'|'M']]
     */
    public static function buscarTodasDatasApoioPeriodo(int $emprId, string $periodoIni, string $periodoFim, ?int $centroTrabId = null): array
    {
        $filtroCtId = $centroTrabId 
            ? "AND (v.ID_CENTRO_TRAB = " . intval($centroTrabId) . " OR vd.ID_CENTRO_TRAB_APOIO = " . intval($centroTrabId) . ")"
            : '';
        
        $campoTipoCalculo = self::colunaExiste() ? "NVL(vd.TIPO_CALCULO, 'T') AS TIPO_CALCULO" : "'T' AS TIPO_CALCULO";
        
        $params = [
            'id_empr' => intval($emprId),
            'periodo_ini' => "'" . $periodoIni . "'",
            'periodo_fim' => "'" . $periodoFim . "'",
            'filtro_centro' => $filtroCtId,
            'campo_tipo_calculo' => $campoTipoCalculo
        ];
        
        try {
            $result = Database::switchParams('focco', $params, 'vinculodata.apoio.buscarTodasDatasPeriodo', true);
            $rows = $result['retorno'] ?? [];
            
            $datasApoio = [];
            foreach ($rows as $row) {
                $funcId = (int)$row['ID_FUNCIONARIO'];
                $data = $row['DATA'];
                $centroApoio = $row['ID_CENTRO_TRAB_APOIO'] ?? $row['CENTRO_PRINCIPAL'];
                $tipoCalculo = $row['TIPO_CALCULO'] ?? 'T';
                
                if (!isset($datasApoio[$funcId])) {
                    $datasApoio[$funcId] = [];
                }
                $datasApoio[$funcId][$data] = [
                    'centro' => (int)$centroApoio,
                    'tipo_calculo' => $tipoCalculo
                ];
            }
            
            return $datasApoio;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
