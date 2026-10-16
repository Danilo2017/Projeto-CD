<?php

namespace src\handlers\CD;

use src\models\CD\ProjecaoCarga;
use src\models\CD\ProjecaoCargaCD;

/**
 * Projeção de Carga do CD
 * Clientes fixos (TCLIENTES.ID): 5210 = Gazin Colchões, 5487 = Gazin Molas
 */
class ProjecaoCargaCDHandler
{
    const CLIENTES   = [5210, 5487];
    const WMS_SCHEMA = 'FOCCOWMS85';

    private static function cliIds(): string
    {
        return implode(', ', array_map('intval', self::CLIENTES));
    }

    public static function listar(string $dataInicio, string $dataFim): array
    {
        $lista = ProjecaoCargaCD::listar(self::cliIds(), $dataInicio, $dataFim, self::WMS_SCHEMA);

        // Volume e peso vêm do WMS; se uma consulta falhar a lista sai sem aquela coluna preenchida
        $numCargas = array_column($lista, 'NUM_CARGA');
        try {
            $volumes = ProjecaoCargaCD::volumesPorCarga($numCargas, self::WMS_SCHEMA);
        } catch (\Throwable $t) {
            $volumes = [];
        }
        try {
            $pesos = ProjecaoCargaCD::pesosPorCarga($numCargas, self::WMS_SCHEMA);
        } catch (\Throwable $t) {
            $pesos = [];
        }
        foreach ($lista as &$r) {
            $r['VOLUME'] = $volumes[(int) $r['NUM_CARGA']] ?? null;
            $r['PESO']   = $pesos[(int) $r['NUM_CARGA']] ?? null;
        }
        unset($r);

        // Auto-transição FT/FP → AGUARDANDO DOCUMENTAÇÃO (mesma regra da Projeção de Carga),
        // agrupada por empresa pois a lista do CD mistura empresas
        $erroTransicao = null;
        $naoTransicionar = ['AGUARDANDO DOCUMENTAÇÃO', 'FINALIZADO'];
        $porEmpresa = [];
        foreach ($lista as &$r) {
            if (in_array($r['POS_PLC'] ?? '', ['FT', 'FP'])
                && !in_array($r['SITUACAO_CAMINHAO'] ?? '', $naoTransicionar)) {
                $porEmpresa[(int) $r['EMPR_ID']][] = $r['NUM_CARGA'];
                $r['SITUACAO_CAMINHAO'] = 'AGUARDANDO DOCUMENTAÇÃO';
            }
        }
        unset($r);

        foreach ($porEmpresa as $emprId => $numCargas) {
            try {
                ProjecaoCarga::marcarAguardandoDocumentacao($emprId, $numCargas);
            } catch (\Throwable $t) {
                $erroTransicao = $t->getMessage();
            }
        }

        $ret = ['success' => true, 'data' => $lista, 'total' => count($lista)];
        if ($erroTransicao) $ret['_transicao_erro'] = $erroTransicao;
        return $ret;
    }

    /**
     * Empresa da carga, validando que ela pertence aos clientes do CD
     *
     * @throws \Exception 403 se a carga não é dos clientes do CD
     */
    public static function empresaDaCarga(int $numCarga): int
    {
        if ($numCarga <= 0) {
            throw new \Exception('Número da carga é obrigatório.', 400);
        }

        $empresas = ProjecaoCargaCD::empresasDaCarga(self::cliIds(), $numCarga);
        if (!$empresas) {
            throw new \Exception("Carga {$numCarga} não pertence aos clientes do CD.", 403);
        }
        if (count($empresas) > 1) {
            throw new \Exception("Carga {$numCarga} existe em mais de uma empresa.", 409);
        }
        return $empresas[0];
    }
}
