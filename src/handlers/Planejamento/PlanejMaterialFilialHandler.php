<?php

namespace src\handlers\Planejamento;

use src\models\Planejamento\PlanejMaterialFilial as M;

class PlanejMaterialFilialHandler
{
    /** Sessão (geração) ativa do usuário; o front nunca informa a sessão. */
    private static function sessao(string $usuario): int
    {
        $par = M::parametros($usuario);
        if (!$par) throw new \Exception('Gere a consulta primeiro.', 409);
        return (int) $par['SESSAO'];
    }

    private static function linha(string $usuario, $id): array
    {
        $l = M::linha(self::sessao($usuario), (int) $id);
        if (!$l) throw new \Exception('Item não encontrado nesta geração. Gere a consulta novamente.', 409);
        return $l;
    }

    public static function filtros(string $usuario): array
    {
        return ['empresas' => M::empresas(), 'parametros' => M::parametros($usuario)];
    }

    public static function gerar(array $d, string $usuario): array
    {
        set_time_limit(600);
        $permitidas = array_map(fn($e) => (int) $e['ID'], M::empresas());
        $empresas = array_values(array_intersect(array_map('intval', explode(',', M::listaInt($d['empresas'] ?? ''))), $permitidas));
        if (!$empresas) throw new \Exception('Selecione ao menos uma empresa.', 422);

        $iso = '/^\d{4}-\d{2}-\d{2}$/';
        $ini = (string) ($d['dt_ini'] ?? '');
        $fim = (string) ($d['dt_fim'] ?? '');
        if (!preg_match($iso, $ini) || !preg_match($iso, $fim)) throw new \Exception('Informe o período.', 422);
        if ($fim < $ini) throw new \Exception('O período final deve ser maior que o inicial.', 422);

        M::gerar($usuario, implode(',', $empresas), $ini, $fim);
        return self::listar($usuario);
    }

    public static function listar(string $usuario): array
    {
        $par = M::parametros($usuario);
        return ['parametros' => $par, 'rows' => $par ? M::listar((int) $par['SESSAO']) : []];
    }

    public static function selecionar(array $d, string $usuario): array
    {
        $ids = M::listaInt($d['ids'] ?? '');
        if ($ids === '') throw new \Exception('Nenhum item informado.', 422);
        $n = M::selecionar(self::sessao($usuario), $ids, !empty($d['selecao']) ? 1 : 0);
        return ['success' => true, 'alterados' => $n];
    }

    public static function detalhe(array $d, string $usuario): array
    {
        switch ((string) ($d['tipo'] ?? '')) {
            case 'pedidos':
                return ['rows' => M::pedidos(self::linha($usuario, $d['id'] ?? 0))];
            case 'pedido_itens':
                $tipo = ($d['tipo_pedido'] ?? '') === 'PDV' ? 'PDV' : 'PDC';
                return ['rows' => M::pedidoItens($tipo, (int) ($d['ped_id'] ?? 0))];
            case 'almox':
                return ['rows' => M::almoxarifados(self::linha($usuario, $d['id'] ?? 0))];
            case 'necessidade':
                $l = self::linha($usuario, $d['id'] ?? 0);
                return ['necessidade' => M::necessidade(self::sessao($usuario), (int) $l['ID'])];
        }
        throw new \Exception('Tipo de detalhe inválido.', 422);
    }

    public static function resumo(string $usuario): array
    {
        return ['rows' => M::resumo(self::sessao($usuario))];
    }

    public static function alterarQtde(array $d, string $usuario): array
    {
        $q = $d['qtde'] ?? null;
        if (!is_numeric($q) || (float) $q < 0 || (float) $q > 999999999) throw new \Exception('Quantidade inválida.', 422);
        $l = self::linha($usuario, $d['id'] ?? 0);
        M::alterarQtde(self::sessao($usuario), (int) $l['ID'], round((float) $q, 3));
        return ['success' => true];
    }

    public static function interfabrica(string $usuario): array
    {
        set_time_limit(600);
        $sessao = self::sessao($usuario);
        if (!M::resumo($sessao)) throw new \Exception('Nenhum item selecionado para gerar interfábrica.', 422);
        M::gerarInterfabrica($sessao);
        return ['success' => true, 'mensagens' => M::mensagens($sessao)];
    }

    public static function mensagens(string $usuario): array
    {
        return ['rows' => M::mensagens(self::sessao($usuario))];
    }
}
