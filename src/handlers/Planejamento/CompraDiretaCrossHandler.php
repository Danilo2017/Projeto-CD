<?php

namespace src\handlers\Planejamento;

use src\models\Planejamento\CompraDiretaCross as M;
use src\utils\DashboardCache;

/**
 * Compra Direta Crossdocking. A geração fica guardada por usuário (cache em arquivo, 12h), no lugar da
 * tabela de sessão do OTM; marcação e "Qtde Demanda" ficam na tela e vão junto no "Gerar Linha".
 */
class CompraDiretaCrossHandler
{
    private const TTL = 43200;

    private static function chaveCache(string $usuario): string
    {
        return 'planejamento_cdc_' . strtoupper($usuario);
    }

    private static function geracao(string $usuario): array
    {
        $g = DashboardCache::get(self::chaveCache($usuario));
        if (!$g) throw new \Exception('Gere a consulta primeiro.', 409);
        return $g;
    }

    /** Linha da geração do usuário (o front só manda a chave; IDs vêm do servidor). */
    private static function linha(string $usuario, $chave): array
    {
        $g = self::geracao($usuario);
        foreach ($g['rows'] as $r) if ($r['CHAVE'] === (string) $chave) return $r;
        throw new \Exception('Item não encontrado nesta geração. Gere a consulta novamente.', 409);
    }

    private static function lista(string $v, string $re, string $msg): array
    {
        $out = [];
        foreach (preg_split('/[,;\s]+/', strtoupper(trim($v))) as $p) {
            if ($p === '') continue;
            if (!preg_match($re, $p)) throw new \Exception("$msg: $p", 422);
            $out[] = $p;
        }
        return array_values(array_unique($out));
    }

    public static function filtros(string $usuario): array
    {
        $g = DashboardCache::get(self::chaveCache($usuario));
        return ['empresas' => M::empresas(), 'parametros' => $g['par'] ?? null];
    }

    public static function gerar(array $d, string $usuario): array
    {
        set_time_limit(900);
        $empresa = (int) ($d['empresa'] ?? 0);
        if (!in_array($empresa, array_map(fn($e) => (int) $e['ID'], M::empresas()), true)) throw new \Exception('Selecione a empresa.', 422);
        $classes = self::lista((string) ($d['classificacao'] ?? ''), '/^[0-9.%_]{1,30}$/', 'Classificação inválida');
        $itens   = self::lista((string) ($d['item'] ?? ''), '/^[0-9A-Z.\-]{1,20}$/', 'Item inválido');
        if (!$classes && !$itens) throw new \Exception('Informe a classificação ou o item.', 422);
        if (count($itens) > 500) throw new \Exception('Informe no máximo 500 itens.', 422);

        $t = microtime(true);
        $rows = M::gerar($empresa, $classes, $itens);
        $par = ['empresa' => $empresa, 'classificacao' => implode(',', $classes), 'item' => implode(',', $itens),
                'dt_geracao' => date('d/m/Y H:i'), 'segundos' => (int) round(microtime(true) - $t), 'linhas' => count($rows)];
        DashboardCache::set(self::chaveCache($usuario), ['par' => $par, 'rows' => $rows], self::TTL);
        return ['parametros' => $par, 'rows' => $rows];
    }

    public static function listar(string $usuario): array
    {
        $g = DashboardCache::get(self::chaveCache($usuario));
        return ['parametros' => $g['par'] ?? null, 'rows' => $g['rows'] ?? []];
    }

    public static function detalhe(array $d, string $usuario): array
    {
        switch ((string) ($d['tipo'] ?? '')) {
            case 'perfil':
                $l = self::linha($usuario, $d['chave'] ?? '');
                return ['rows' => self::projetar(M::perfil((int) $l['EMPR_ID'], (int) $l['ITEM_EMPR_ID'],
                                                             $l['MASC_ID'] === null ? null : (int) $l['MASC_ID']))];
            case 'pedidos':
                $l = self::linha($usuario, $d['chave'] ?? '');
                return ['rows' => M::pedidos((int) $l['EMPR_ID'], (int) $l['ITEM_ID'], $l['MASC_ID'] === null ? null : (int) $l['MASC_ID'])];
            case 'pedido_itens':
                return ['rows' => M::pedidoItens((int) ($d['ped_id'] ?? 0))];
        }
        throw new \Exception('Tipo de detalhe inválido.', 422);
    }

    /**
     * "Projetado" do Perfil, como no OTM: parte do maior saldo e, data a data,
     * saldo = (saldo − demandas − OFA/OFM) + (OFP + OCL). Datas repetidas ficam com o último valor do dia.
     */
    private static function projetar(array $rows): array
    {
        if (!$rows) return [];
        $saldo = max(array_map(fn($r) => (float) $r['ESTOQUE'], $rows));
        $porData = [];
        foreach ($rows as $r) {
            $saldo = ($saldo - (float) $r['DEMANDA'] - (float) $r['OFA_OFM']) + ((float) $r['OFP'] + (float) $r['OCL']);
            $porData[$r['DATA_ORD']] = $saldo;
        }
        foreach ($rows as &$r) $r['PROJETADO'] = $porData[$r['DATA_ORD']];
        return $rows;
    }

    public static function alterarStatus(array $d, string $usuario): array
    {
        $status = (string) ($d['status'] ?? '');
        if (!in_array($status, ['A', 'N'], true)) throw new \Exception('Status inválido.', 422);
        $obs = mb_strtoupper(mb_substr(trim((string) ($d['obs'] ?? '')), 0, 100));
        $l = self::linha($usuario, $d['chave'] ?? '');
        M::alterarStatus((int) $l['ITEM_ID'], $l['MASC_ID'] === null ? null : (int) $l['MASC_ID'], $l['MASCARA'], $status, $obs, $usuario);

        // atualiza a geração guardada (mesmo item/máscara)
        $g = self::geracao($usuario);
        foreach ($g['rows'] as &$r) {
            if ($r['ITEM_ID'] === $l['ITEM_ID'] && $r['MASCARA'] === $l['MASCARA']) {
                $r['TIPO_PRODUTO'] = $status === 'N' ? 'N' : null;
                $r['OBS'] = $obs !== '' ? $obs : null;
            }
        }
        unset($r);
        DashboardCache::set(self::chaveCache($usuario), $g, self::TTL);
        return ['success' => true, 'tipo_produto' => $status === 'N' ? 'N' : null, 'obs' => $obs !== '' ? $obs : null];
    }

    /** Planos (2 e 6) e centros de custo da empresa da geração, para o "Gerar Linha". */
    public static function opcoesLinha(string $usuario): array
    {
        $emp = (int) self::geracao($usuario)['par']['empresa'];
        return ['planos' => M::planos($emp), 'centros_custo' => M::centrosCusto($emp),
                'em_calculo' => M::andamentoMrp($emp, 0, M::agoraBanco())['em_calculo']];
    }

    // ── Gerar Linha: demanda + MRP em segundo plano + ordens geradas + liberação ──

    private static function chaveMrp(string $usuario): string
    {
        return 'planejamento_cdc_mrp_' . strtoupper($usuario);
    }

    private static function estadoMrp(string $usuario): ?array
    {
        return DashboardCache::get(self::chaveMrp($usuario));
    }

    private static function gravarEstadoMrp(string $usuario, array $e): void
    {
        DashboardCache::set(self::chaveMrp($usuario), $e, 86400);
    }

    /**
     * "Gerar Linha": cria a demanda independente dos itens marcados e devolve a resposta na hora;
     * o MRP do plano roda em seguida num processo em segundo plano, sem prender a tela.
     */
    public static function gerarLinha(array $d, string $usuario): array
    {
        $atual = self::estadoMrp($usuario);
        if ($atual && ($atual['status'] ?? '') === 'rodando') {
            throw new \Exception("Ainda está rodando o MRP da demanda {$atual['num_demanda']}. Aguarde terminar.", 409);
        }
        $g = self::geracao($usuario);
        $emp = (int) $g['par']['empresa'];

        $plano = null;
        foreach (M::planos($emp) as $p) if ((int) $p['ID'] === (int) ($d['plano_id'] ?? 0)) $plano = $p;
        if (!$plano) throw new \Exception('Selecione o plano.', 422);
        $ccOk = in_array((int) ($d['cc_id'] ?? 0), array_map(fn($c) => (int) $c['ID'], M::centrosCusto($emp)), true);
        if (!$ccOk) throw new \Exception('Selecione o centro de custo.', 422);

        $itens = is_array($d['itens'] ?? null) ? $d['itens'] : [];
        if (!$itens) throw new \Exception('Marque ao menos um item.', 422);
        $porChave = array_column($g['rows'], null, 'CHAVE');
        $linhas = [];
        foreach ($itens as $it) {
            $r = $porChave[(string) ($it['chave'] ?? '')] ?? null;
            if (!$r) throw new \Exception('Item marcado não está na geração. Gere a consulta novamente.', 409);
            $q = $it['qtde'] ?? null;
            if (!is_numeric($q) || (float) $q < 0 || (float) $q > 999999999) throw new \Exception("Quantidade inválida no item {$r['COD_ITEM']}.", 422);
            // mesma validação do OTM: item marcado sem quantidade bloqueia a geração
            if ((float) $q == 0) throw new \Exception('Favor verificar itens selecionados sem quantidade para a demanda!', 422);
            $linhas[] = ['item_empr_id' => (int) $r['ITEM_EMPR_ID'], 'masc_id' => $r['MASC_ID'] === null ? null : (int) $r['MASC_ID'],
                         'qtde' => round((float) $q, 6), 'cod_item' => $r['COD_ITEM'], 'desc' => $r['DESC_ITEM'], 'mascara' => $r['MASCARA']];
        }
        $mrp = M::jobMrp($emp, (int) $plano['COD_PLANO']);
        if ($mrp === null) throw new \Exception('Não existe job para execução do plano!', 422);

        $inicio = M::agoraBanco();
        $nums = M::criarDemanda($emp, (int) $d['cc_id'], $linhas);
        foreach ($linhas as $i => &$l) $l['num_demanda'] = $nums[$i];
        unset($l);
        $validos = array_values(array_filter($nums));
        $num = count($validos) === 1 ? (string) $validos[0]
            : (max($validos) - min($validos) === count($validos) - 1 ? min($validos) . ' a ' . max($validos) : implode(', ', $validos));
        $estado = [
            'num_demanda' => $num, 'empresa' => $emp, 'plano_id' => (int) $plano['ID'], 'plano_cod' => (int) $plano['COD_PLANO'],
            'plano_desc' => $plano['DESCRICAO'], 'mrp_id' => $mrp, 'linhas' => $linhas, 'inicio' => $inicio,
            'status' => 'rodando', 'erro' => null, 'fim' => null, 'liberadas' => [],
        ];
        self::gravarEstadoMrp($usuario, $estado);
        self::dispararMrp($usuario);

        return [
            'success' => true, 'num_demanda' => $num, 'itens' => count($linhas),
            'mensagem' => "Demanda $num criada. Rodando o MRP do plano {$plano['COD_PLANO']} - {$plano['DESCRICAO']}...",
        ];
    }

    /** Dispara o MRP num processo PHP de linha de comando em segundo plano (a requisição volta na hora). */
    private static function dispararMrp(string $usuario): void
    {
        if (!preg_match('/^[A-Za-z0-9._-]{1,60}$/', $usuario)) throw new \Exception('Usuário inválido para rodar o MRP.', 500);
        $script = dirname(__DIR__, 2) . '/cli/compra-direta-cross-mrp.php';
        exec(sprintf('nohup %s -d xdebug.mode=off %s %s > /dev/null 2>&1 &', escapeshellarg(PHP_BINDIR . '/php'), escapeshellarg($script), escapeshellarg($usuario)));
    }

    /** Executado pelo processo em segundo plano: roda o MRP e grava o resultado para a tela. */
    public static function executarMrp(string $usuario): void
    {
        $estado = self::estadoMrp($usuario);
        if (!$estado || $estado['status'] !== 'rodando') return;
        try {
            M::rodarMrp($estado['empresa'], $estado['plano_id'], $estado['mrp_id']);
            $and = M::andamentoMrp($estado['empresa'], $estado['plano_id'], $estado['inicio']);
            $interrompido = array_values(array_filter($and['etapas'], fn($e) => (int) $e['NIVEL'] === 9));
            $estado['status'] = $interrompido ? 'erro' : 'ok';
            $estado['erro'] = $interrompido ? 'O MRP foi interrompido: ' . $interrompido[0]['DESCRICAO'] : null;
        } catch (\Throwable $e) {
            $estado['status'] = 'erro';
            $estado['erro'] = 'Erro ao rodar o MRP: ' . $e->getMessage();
        }
        $estado['fim'] = M::agoraBanco();
        self::gravarEstadoMrp($usuario, $estado);
    }

    /** Andamento do MRP da última demanda do usuário e, quando terminar, as ordens geradas para ela. */
    public static function mrpStatus(string $usuario): array
    {
        $e = self::estadoMrp($usuario);
        if (!$e) return ['estado' => null];

        $and = M::andamentoMrp($e['empresa'], $e['plano_id'], $e['inicio']);
        // segurança: se o processo do servidor caiu mas o cálculo terminou, considera concluído
        if ($e['status'] === 'rodando' && !$and['em_calculo'] && $and['ultima_geracao'] && $and['ultima_geracao'] > $e['inicio']) {
            $e['status'] = 'ok';
            $e['fim'] = $and['ultima_geracao'];
            self::gravarEstadoMrp($usuario, $e);
        } elseif ($e['status'] === 'rodando' && !$and['em_calculo'] && strtotime(M::agoraBanco()) - strtotime($e['inicio']) > 3600) {
            $e['status'] = 'erro';
            $e['erro'] = 'O MRP não respondeu em 60 minutos. Confira o plano pelo Focco.';
            self::gravarEstadoMrp($usuario, $e);
        }
        $out = ['estado' => [
            'num_demanda' => $e['num_demanda'], 'plano_cod' => $e['plano_cod'], 'plano_desc' => $e['plano_desc'],
            'status' => $e['status'], 'erro' => $e['erro'], 'inicio' => $e['inicio'], 'fim' => $e['fim'],
        ], 'etapas' => $and['etapas'],
           'fila' => array_values(array_filter($and['em_calculo'], fn($c) => (int) $c['PLANO_ID'] !== (int) $e['plano_id']))];

        if ($e['status'] === 'ok') $out['itens'] = self::ordensDoEstado($e);
        if ($e['liberadas']) $out['liberadas'] = M::liberacoes($e['liberadas']);
        return $out;
    }

    /** Por item da demanda: a linha DI e as ordens planejadas, com a quantidade ajustada ao lote. */
    private static function ordensDoEstado(array $e): array
    {
        $itens = [];
        foreach ($e['linhas'] as $l) {
            $numLinha = (int) ($l['num_demanda'] ?? $e['num_demanda']);
            $o = $numLinha ? M::ordensDaDemanda($e['plano_id'], $numLinha, $l['item_empr_id'], $l['masc_id'])
                           : ['demanda' => null, 'ordens' => []];
            foreach ($o['ordens'] as &$ord) {
                $ord['LIBERAVEL'] = $ord['TIPO'] === 'OCP' && !in_array((string) $ord['STATUS_LIB'], ['1', '2'], true);
                $ord['QTDE_LOTE'] = $ord['LIBERAVEL']
                    ? M::qtdeLote((int) $ord['EMPR_ID'], $e['plano_id'], (int) $ord['ITPL_ID'], $ord['MASC_ID'] === null ? null : (int) $ord['MASC_ID'], (float) $ord['QTDE'])
                    : (float) $ord['QTDE'];
            }
            unset($ord);
            $itens[] = ['num_demanda' => $numLinha, 'cod_item' => $l['cod_item'], 'desc' => $l['desc'], 'mascara' => $l['mascara'], 'qtde_demanda' => $l['qtde'],
                        'demanda' => $o['demanda'], 'ordens' => $o['ordens'],
                        'demandas_dia' => $o['demandas_dia'] ?? [], 'proj_antes' => $o['proj_antes'] ?? null];
        }
        return $itens;
    }

    /** Libera para o comprador (FGAZIN_SUP004 -> FGAZIN_SUP005) as ordens escolhidas da demanda. */
    public static function liberarOrdens(array $d, string $usuario): array
    {
        $e = self::estadoMrp($usuario);
        if (!$e || $e['status'] !== 'ok') throw new \Exception('O MRP da demanda ainda não terminou.', 409);
        $sel = is_array($d['ordens'] ?? null) ? $d['ordens'] : [];
        if (!$sel) throw new \Exception('Selecione ao menos uma ordem.', 422);

        // só ordens desta demanda (o front manda o ID do perfil; conferimos contra a lista do servidor)
        $permitidas = [];
        foreach (self::ordensDoEstado($e) as $it) foreach ($it['ordens'] as $o) if ($o['LIBERAVEL']) $permitidas[(string) $o['PERFIL_ID']] = $o;

        $hoje = date('Y-m-d');
        $liberadas = [];
        foreach ($sel as $s) {
            $o = $permitidas[(string) ($s['perfil_id'] ?? '')] ?? null;
            if (!$o) throw new \Exception('Ordem não pertence a esta demanda ou já foi liberada.', 409);
            $q = $s['qtde'] ?? null;
            if (!is_numeric($q) || (float) $q <= 0 || (float) $q > 999999999) throw new \Exception("Quantidade inválida na ordem {$o['ORDEM']}.", 422);
            $dt = (string) ($s['dt_entrega'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) || !checkdate((int) substr($dt, 5, 2), (int) substr($dt, 8, 2), (int) substr($dt, 0, 4))) {
                throw new \Exception("Data de entrega inválida na ordem {$o['ORDEM']}.", 422);
            }
            if ($dt < $hoje) throw new \Exception("A data de entrega da ordem {$o['ORDEM']} não pode ser anterior a hoje.", 422);
            // mesma regra de lote da FGAZIN_SUP004
            $qLote = M::qtdeLote((int) $o['EMPR_ID'], $e['plano_id'], (int) $o['ITPL_ID'], $o['MASC_ID'] === null ? null : (int) $o['MASC_ID'], (float) $q);
            M::liberarOrdem((int) $o['PERFIL_ID'], $qLote, $dt, $usuario);
            $liberadas[] = (int) $o['PERFIL_ID'];
        }
        $e['liberadas'] = array_values(array_unique(array_merge($e['liberadas'], $liberadas)));
        self::gravarEstadoMrp($usuario, $e);
        return ['success' => true, 'liberadas' => M::liberacoes($liberadas)];
    }

    /** Fecha o acompanhamento da última demanda (não mexe em nada no banco). */
    public static function concluirMrp(string $usuario): array
    {
        $e = self::estadoMrp($usuario);
        if ($e && $e['status'] === 'rodando') throw new \Exception('O MRP ainda está rodando.', 409);
        DashboardCache::forget(self::chaveMrp($usuario));
        return ['success' => true];
    }
}
