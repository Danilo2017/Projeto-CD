<?php

namespace src\models\CD;

use core\Database;

class ReciboDescarga
{
    public static function proximoNumeroRecibo()
    {
        $result = Database::switchParams('focco', [], 'cd.recibo.proximoNumero', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0]['PROXIMO'] ?? null;
    }

    public static function inserir($dados)
    {
        $numeroRecibo = self::proximoNumeroRecibo();

        $agendamentoId = $dados['agendamento_id'];
        $empresaPagadora = $dados['empresa_pagadora'];
        $cnpjCpf = isset($dados['cnpj_cpf']) ? $dados['cnpj_cpf'] : null;
        $valorPago = floatval($dados['valor_pago']);
        $formaPagamento = isset($dados['forma_pagamento']) ? $dados['forma_pagamento'] : 'DINHEIRO';
        $observacoes = isset($dados['observacoes']) ? $dados['observacoes'] : null;
        $usuarioEmissao = isset($dados['usuario_emissao']) ? $dados['usuario_emissao'] : 'SISTEMA';

        $params = [
            'numero_recibo' => intval($numeroRecibo),
            'agendamento_id' => intval($agendamentoId),
            'empresa_pagadora' => "'" . str_replace("'", "''", $empresaPagadora) . "'",
            'cnpj_cpf' => $cnpjCpf !== null ? "'" . str_replace("'", "''", $cnpjCpf) . "'" : 'NULL',
            'valor_pago' => $valorPago,
            'forma_pagamento' => "'" . str_replace("'", "''", $formaPagamento) . "'",
            'observacoes' => $observacoes !== null ? "'" . str_replace("'", "''", $observacoes) . "'" : 'NULL',
            'usuario_emissao' => "'" . str_replace("'", "''", $usuarioEmissao) . "'"
        ];

        $result = Database::switchParams('focco', $params, 'cd.recibo.inserir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        // Buscar o ID pelo numero_recibo
        $paramsId = ['numero_recibo' => intval($numeroRecibo)];
        $resultId = Database::switchParams('focco', $paramsId, 'cd.recibo.buscarUltimoId', true);
        $id = ($resultId['retorno'][0]['ID'] ?? null);

        return [
            'id' => $id,
            'numero_recibo' => $numeroRecibo
        ];
    }

    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'cd.recibo.buscarPorId', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function buscarPorNumero($numero)
    {
        $params = ['numero' => intval($numero)];
        $result = Database::switchParams('focco', $params, 'cd.recibo.buscarPorNumero', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'][0] ?? null;
    }

    public static function listarPorAgendamento($agendamentoId)
    {
        $params = ['agendamento_id' => intval($agendamentoId)];
        $result = Database::switchParams('focco', $params, 'cd.recibo.listarPorAgendamento', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    public static function listarRecibosMes()
    {
        $result = Database::switchParams('focco', [], 'cd.recibo.listarRecibosMes', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }
}

