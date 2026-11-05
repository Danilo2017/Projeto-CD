<?php

namespace src\handlers\Planejamento;

use src\models\Planejamento\FollowUpPedidoCompra as M;

class FollowUpPedidoCompraHandler
{
    public static function filtros(): array
    {
        return [
            'empresas'       => M::empresas(),
            'classificacoes' => M::classificacoes(),
            'tiposNota'      => M::tiposNota(),
        ];
    }

    public static function fornecedores(array $d): array
    {
        $termo = trim((string) ($d['termo'] ?? ''));
        if (mb_strlen($termo) < 2) return ['rows' => []];
        return ['rows' => M::fornecedores($termo)];
    }

    public static function mascaras(array $d): array
    {
        $cod      = trim((string) ($d['cod_item'] ?? ''));
        $empresas = M::listaInt($d['empresas'] ?? '');
        if ($cod === '' || $empresas === '') return ['rows' => []];
        return ['rows' => M::mascaras($cod, $empresas)];
    }

    public static function listar(array $d): array
    {
        set_time_limit(300);

        $empresas = M::listaInt($d['empresas'] ?? '');
        if ($empresas === '') throw new \Exception('Selecione ao menos uma empresa.', 422);

        $sit = (string) ($d['situacao'] ?? 'P');
        $sit = in_array($sit, ['P', 'A'], true) ? $sit : null;

        $codItem = trim((string) ($d['cod_item'] ?? ''));
        if ($codItem !== '' && !preg_match('/^[0-9A-Za-z.\-]{1,20}$/', $codItem)) {
            throw new \Exception('Código do item inválido.', 422);
        }

        return ['rows' => M::listar(
            $empresas,
            $sit,
            M::listaInt($d['classificacoes'] ?? ''),
            M::listaInt($d['fornecedores'] ?? ''),
            M::listaInt($d['tipos_nota'] ?? ''),
            $codItem === '' ? null : $codItem,
            M::listaInt($d['mascaras'] ?? '')
        )];
    }
}
