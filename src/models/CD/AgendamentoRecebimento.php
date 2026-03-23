<?php

namespace src\models\CD;

use core\Database;

class AgendamentoRecebimento
{
    public static function listarPendentes()
    {
        $result = Database::switchParams('focco', [], 'cd.agendamento.listarPendentes', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return $result['retorno'];
    }

    public static function listarTodos()
    {
        $result = Database::switchParams('focco', [], 'cd.agendamento.listarTodos', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        $recebimentos = [];
        foreach ($result['retorno'] as $row) {
            $recebimentos[] = [
                'id' => $row['ID'],
                'data' => $row['DATA'],
                'hora' => $row['HORA'],
                'fornecedor' => $row['FORNECEDOR'],
                'placa' => $row['PLACA'],
                'descricao' => $row['DESCRICAO'] ?: '',
                'peso' => $row['PESO'],
                'volume' => $row['VOLUME'],
                'recebido' => $row['RECEBIDO'] === 'S'
            ];
        }
        return $recebimentos;
    }

    public static function verificarDuplicataRecente($input)
    {
        $hora = isset($input['hora']) && $input['hora'] ? $input['hora'] : '00:00';
        $data_hora = $input['data'] . ' ' . $hora;
        $fornecedor = $input['fornecedor'];
        $descricao = isset($input['descricao']) ? $input['descricao'] : '';
        $placa = isset($input['placa']) && trim($input['placa']) !== '' ? strtoupper(trim($input['placa'])) : null;
        $placaCheck = $placa !== null ? $placa : 'VAZIO';

        $params = [
            'data_hora' => "'" . $data_hora . "'",
            'fornecedor' => "'" . str_replace("'", "''", $fornecedor) . "'",
            'descricao' => "'" . str_replace("'", "''", $descricao) . "'",
            'placa' => "'" . str_replace("'", "''", $placaCheck) . "'"
        ];

        $result = Database::switchParams('focco', $params, 'cd.agendamento.verificarDuplicata', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        $row = $result['retorno'][0] ?? null;
        return $row && $row['QTD'] > 0;
    }

    public static function inserir($input)
    {
        $hora = isset($input['hora']) && $input['hora'] ? $input['hora'] : '00:00';
        $data_hora = $input['data'] . ' ' . $hora;
        $fornecedor = $input['fornecedor'];
        $placa = isset($input['placa']) && trim($input['placa']) !== '' ? strtoupper(trim($input['placa'])) : null;
        $descricao = isset($input['descricao']) ? $input['descricao'] : '';
        $peso = isset($input['peso']) && $input['peso'] !== null ? floatval($input['peso']) : null;
        $volume = isset($input['volume']) && $input['volume'] !== null ? floatval($input['volume']) : null;
        $status = isset($input['recebido']) && $input['recebido'] ? 'FINALIZADO' : 'PENDENTE';

        $params = [
            'placa' => $placa !== null ? "'" . str_replace("'", "''", $placa) . "'" : 'NULL',
            'data_hora' => "'" . $data_hora . "'",
            'fornecedor' => "'" . str_replace("'", "''", $fornecedor) . "'",
            'descricao' => "'" . str_replace("'", "''", $descricao) . "'",
            'peso' => $peso !== null ? $peso : 'NULL',
            'volume' => $volume !== null ? $volume : 'NULL',
            'status' => "'" . $status . "'"
        ];

        $result = Database::switchParams('focco', $params, 'cd.agendamento.inserir', true, true,
            ['table' => 'FOCCO3I.TGAZIN_AGENDAMENTO_RECEBIMENTO']);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }

        $id = null;
        if (!empty($result['retorno'])) {
            foreach ($result['retorno'] as $row) {
                if (isset($row['currval'])) {
                    $id = $row['currval'];
                    break;
                }
            }
        }
        return $id;
    }

    public static function atualizar($input)
    {
        $id = $input['id'];
        $hora = isset($input['hora']) && $input['hora'] ? $input['hora'] : '00:00';
        $data_hora = $input['data'] . ' ' . $hora;
        $fornecedor = $input['fornecedor'];
        $placa = isset($input['placa']) && trim($input['placa']) !== '' ? strtoupper(trim($input['placa'])) : null;
        $descricao = isset($input['descricao']) ? $input['descricao'] : '';
        $peso = isset($input['peso']) && $input['peso'] !== null ? floatval($input['peso']) : null;
        $volume = isset($input['volume']) && $input['volume'] !== null ? floatval($input['volume']) : null;
        $status = isset($input['recebido']) && $input['recebido'] ? 'FINALIZADO' : 'PENDENTE';

        $params = [
            'id' => intval($id),
            'placa' => $placa !== null ? "'" . str_replace("'", "''", $placa) . "'" : 'NULL',
            'data_hora' => "'" . $data_hora . "'",
            'fornecedor' => "'" . str_replace("'", "''", $fornecedor) . "'",
            'descricao' => "'" . str_replace("'", "''", $descricao) . "'",
            'peso' => $peso !== null ? $peso : 'NULL',
            'volume' => $volume !== null ? $volume : 'NULL',
            'status' => "'" . $status . "'"
        ];

        $result = Database::switchParams('focco', $params, 'cd.agendamento.atualizar', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    public static function excluir($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'cd.agendamento.excluir', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        return true;
    }

    public static function alterarStatus($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'cd.agendamento.buscarStatus', true);
        if ($result['error']) {
            throw new \Exception($result['error']);
        }
        $row = $result['retorno'][0] ?? null;
        if (!$row) {
            throw new \Exception('Registro não encontrado');
        }

        $novoStatus = ($row['STATUS'] === 'FINALIZADO') ? 'PENDENTE' : 'FINALIZADO';

        $paramsUpdate = [
            'status' => "'" . $novoStatus . "'",
            'id' => intval($id)
        ];
        $resultUpdate = Database::switchParams('focco', $paramsUpdate, 'cd.agendamento.alterarStatus', true);
        if ($resultUpdate['error']) {
            throw new \Exception($resultUpdate['error']);
        }

        return [
            'id' => $id,
            'status' => $novoStatus,
            'recebido' => $novoStatus === 'FINALIZADO'
        ];
    }
}

