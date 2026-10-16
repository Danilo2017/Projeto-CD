<?php

namespace src\models\CD;

use core\Database;

/**
 * Projeção de Carga do CD — somente cargas dos clientes do CD, sem filtro de empresa.
 * Gravações (agendamento, anexos, rota, log) reaproveitam o model ProjecaoCarga.
 */
class ProjecaoCargaCD
{
    public static function listar(string $cliIds, string $dataInicio, string $dataFim, string $wmsSchema): array
    {
        $result = Database::switchParams('focco', [
            'cli_ids'     => $cliIds,
            'data_inicio' => $dataInicio,
            'data_fim'    => $dataFim,
            'wms_schema'  => $wmsSchema,
        ], 'cd.projecao_carga_cd.listar', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    /**
     * Quantidade de volumes (etiquetas das tarefas 4/7) por carga no WMS
     *
     * @param int[] $numCargas
     * @return array [num_carga => qtd_volume]
     */
    public static function volumesPorCarga(array $numCargas, string $wmsSchema): array
    {
        if (!$numCargas) return [];

        // WMS_CARGAS.NUM_CARGA é texto
        $lista = implode(', ', array_map(fn($n) => "'" . (int) $n . "'", $numCargas));
        $result = Database::switchParams('focco', [
            'wms_schema' => $wmsSchema,
            'num_cargas' => $lista,
        ], 'cd.projecao_carga_cd.volumes', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);

        $volumes = [];
        foreach ($result['retorno'] ?: [] as $row) {
            $volumes[(int) $row['NUM_CARGA']] = (int) $row['QTD_VOLUME'];
        }
        return $volumes;
    }

    /**
     * Peso total (qtde executada x peso do item) por carga no WMS
     *
     * @param int[] $numCargas
     * @return array [num_carga => peso_total]
     */
    public static function pesosPorCarga(array $numCargas, string $wmsSchema): array
    {
        if (!$numCargas) return [];

        $lista = implode(', ', array_map(fn($n) => "'" . (int) $n . "'", $numCargas));
        $result = Database::switchParams('focco', [
            'wms_schema' => $wmsSchema,
            'num_cargas' => $lista,
        ], 'cd.projecao_carga_cd.pesos', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);

        $pesos = [];
        foreach ($result['retorno'] ?: [] as $row) {
            $pesos[(int) $row['NUM_CARGA']] = (float) $row['PESO_TOTAL'];
        }
        return $pesos;
    }

    /**
     * Itens da expedição no WMS do CD — o FOCCOWMS85 não tem QTDE_EXECUTADA_ORIGINAL,
     * por isso não usa a SQL da Projeção de Carga
     */
    public static function listarItensExpedicao(int $numCarga, string $wmsSchema): array
    {
        $result = Database::switchParams('focco', [
            'wms_schema' => $wmsSchema,
            'num_carga'  => $numCarga,
        ], 'cd.projecao_carga_cd.itens_expedicao', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    /**
     * Empresas da carga considerando apenas pedidos dos clientes informados
     *
     * @return int[] vazio se a carga não pertence aos clientes
     */
    public static function empresasDaCarga(string $cliIds, int $numCarga): array
    {
        $result = Database::switchParams('focco', [
            'cli_ids'   => $cliIds,
            'num_carga' => $numCarga,
        ], 'cd.projecao_carga_cd.empresa_carga', true);
        if (!empty($result['error'])) throw new \Exception($result['error']);
        return array_map('intval', array_column($result['retorno'] ?: [], 'EMPR_ID'));
    }
}
