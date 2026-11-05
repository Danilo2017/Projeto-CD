<?php

namespace src\handlers\Planejamento;

use src\models\Planejamento\PlanejamentoReposicaoCD;

class PlanejamentoReposicaoCDHandler
{
    private static function int(array $d, string $k, bool $obrig = true): ?int
    {
        $v = $d[$k] ?? null;
        if ($v === null || $v === '') {
            if ($obrig) throw new \Exception("Parâmetro obrigatório: $k.", 422);
            return null;
        }
        if (!is_numeric($v)) throw new \Exception("Parâmetro inválido: $k.", 422);
        return (int) $v;
    }

    private static function str(array $d, string $k): ?string
    {
        $v = trim((string) ($d[$k] ?? ''));
        return $v === '' ? null : $v;
    }

    public static function listar(): array
    {
        return [
            'controle' => PlanejamentoReposicaoCD::controle(),
            'rows'     => PlanejamentoReposicaoCD::listar(),
        ];
    }

    public static function recalcular(string $usuario): array
    {
        set_time_limit(300);
        PlanejamentoReposicaoCD::recalcular($usuario);
        return self::listar();
    }

    public static function detalhe(array $d): array
    {
        switch ((string) ($d['tipo'] ?? '')) {
            case 'demandas':
                return ['rows' => PlanejamentoReposicaoCD::demandas(self::int($d, 'item_id'), self::str($d, 'mascara'))];

            case 'saldo':
                return ['rows' => PlanejamentoReposicaoCD::saldoFilial(
                    self::int($d, 'item_id'),
                    self::int($d, 'item_empr_id'),
                    self::int($d, 'masc_id', false),
                    self::str($d, 'mascara')
                )];

            case 'pedidos':
                return ['rows' => PlanejamentoReposicaoCD::pedidos(self::int($d, 'item_id'), self::int($d, 'masc_id', false))];

            case 'pedido_itens':
                return ['rows' => PlanejamentoReposicaoCD::pedidoItens(self::int($d, 'pedc_id'))];
        }
        throw new \Exception('Tipo de detalhe inválido.', 422);
    }

    public static function planejadores(): array
    {
        return ['rows' => PlanejamentoReposicaoCD::planejadores()];
    }

    public static function alterar(array $d, string $usuario): array
    {
        $id = self::int($d, 'id');

        switch ((string) ($d['tipo'] ?? '')) {
            case 'estoque':
                $seg = self::int($d, 'estq_seg');
                if ($seg < 0 || $seg > 9999999) throw new \Exception('Estoque de segurança deve estar entre 0 e 9.999.999.', 422);
                PlanejamentoReposicaoCD::alterarEstoqueSeg($id, $seg);
                return ['success' => true, 'message' => 'Estoque de segurança alterado.', 'estq_seg' => $seg];

            case 'status':
                $status = (string) ($d['status'] ?? '');
                if (!in_array($status, ['A', 'N'], true)) throw new \Exception('Status inválido.', 422);
                $obs = mb_strtoupper(mb_substr(trim((string) ($d['obs'] ?? '')), 0, 100));
                PlanejamentoReposicaoCD::alterarStatus($id, $status, $obs, $usuario);
                return ['success' => true, 'message' => 'Status alterado.', 'tipo_produto' => $status === 'N' ? 'N' : null, 'obs' => $obs];

            case 'planejador':
                $nome = PlanejamentoReposicaoCD::alterarPlanejador($id, self::int($d, 'planejador_id'));
                return ['success' => true, 'message' => 'Planejador alterado.', 'planejador' => $nome];
        }
        throw new \Exception('Tipo de alteração inválido.', 422);
    }
}
