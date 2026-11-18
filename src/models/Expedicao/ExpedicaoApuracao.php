<?php

namespace src\models\Expedicao;

/**
 * Cálculo da comissão da Expedição, dia a dia (só leitura; nada é gravado).
 *
 * Regras:
 *  - Só paga no dia em que a carga foi fechada no Focco (data do log da TCARGAS, último fechamento); 1 ponto = 1 m³ da carga.
 *  - Cada equipe (Expedição / Separação) da empresa recebe, por dia: m³ do dia × valor por m³ + nº de cargas × valor por carga
 *    (regra da equipe vigente no dia).
 *  - O valor da equipe é dividido igual entre os membros do dia. Falta integral (cadastro de faltas da Expedição) tira o membro
 *    do dia; falta parcial conta meio peso na divisão.
 *  - Funcionário com regra individual vigente (da equipe ou geral) não entra no rateio: recebe valor fixo (por dia com carga
 *    fechada) + m³ do dia × valor por m³ da regra; falta parcial paga 50%. Regra geral (sem equipe) vale uma vez por dia.
 */
class ExpedicaoApuracao
{
    public static function apurar(int $emprId, string $ini, string $fim): array
    {
        $ini = Expedicao::data($ini, 'Data inicial');
        $fim = Expedicao::data($fim, 'Data final');
        if ($fim < $ini) throw new \Exception('A data final não pode ser anterior à inicial.', 422);

        $equipes = Expedicao::equipes($emprId, true);
        $membros = Expedicao::membros($emprId, null, true);
        $regrasE = Expedicao::regrasEquipe($emprId, true);
        $regrasF = Expedicao::regrasFunc($emprId, true);
        $cargas  = Expedicao::cargas($emprId, $ini, $fim);
        $faltas  = Expedicao::faltas($emprId, $ini, $fim, null, true);

        $faltaDia = [];
        foreach ($faltas as $f) {
            $k = $f['ID_FUNCIONARIO'] . '|' . $f['DT_FALTA'];
            if (($faltaDia[$k] ?? '') !== 'I') $faltaDia[$k] = $f['TIPO_FALTA'];
        }

        $dias = [];
        foreach ($cargas as $c) {
            $d = $c['DT_FECHAMENTO'];
            $dias[$d]['m3'] = ($dias[$d]['m3'] ?? 0) + (float) $c['CUBAGEM'];
            $dias[$d]['cargas'] = ($dias[$d]['cargas'] ?? 0) + 1;
        }
        ksort($dias);

        $vigente = static function (array $lista, string $dia, callable $filtro): ?array {
            $melhor = null;
            foreach ($lista as $r) {
                if (!$filtro($r) || $r['DT_VIGENCIA_INI'] > $dia || (!empty($r['DT_VIGENCIA_FIM']) && $r['DT_VIGENCIA_FIM'] < $dia)) continue;
                if ($melhor === null || $r['DT_VIGENCIA_INI'] > $melhor['DT_VIGENCIA_INI']) $melhor = $r;
            }
            return $melhor;
        };

        $linhas = [];
        $avisos = [];
        foreach ($dias as $dia => $tot) {
            $regraGeralUsada = [];
            foreach ($equipes as $eq) {
                $eid = (int) $eq['ID_EQUIPE'];
                $regraEq = $vigente($regrasE, $dia, fn($r) => (int) $r['ID_EQUIPE'] === $eid);
                $valorEquipe = $regraEq
                    ? $tot['m3'] * (float) $regraEq['VLR_M3'] + $tot['cargas'] * (float) $regraEq['VLR_CARGA']
                    : 0.0;

                $doDia = [];
                foreach ($membros as $m) {
                    if ((int) $m['ID_EQUIPE'] !== $eid || $m['DT_INI'] > $dia || (!empty($m['DT_FIM']) && $m['DT_FIM'] < $dia)) continue;
                    $falta = $faltaDia[$m['ID_FUNCIONARIO'] . '|' . $dia] ?? null;
                    if ($falta === 'I') continue;
                    $doDia[] = ['m' => $m, 'peso' => $falta === 'P' ? 0.5 : 1.0];
                }
                if (!$doDia) {
                    if ($regraEq) $avisos["sem-membro|$eid"] = "Equipe \"{$eq['NOME']}\" teve carga fechada e regra, mas nenhum funcionário presente em pelo menos um dia.";
                    continue;
                }
                if (!$regraEq) $avisos["sem-regra|$eid"] = "Equipe \"{$eq['NOME']}\" teve carga fechada sem regra de valor vigente em pelo menos um dia.";

                $rateio = [];
                $somaPesos = 0.0;
                foreach ($doDia as $x) {
                    $fid = (int) $x['m']['ID_FUNCIONARIO'];
                    $ind = $vigente($regrasF, $dia, fn($r) => (int) $r['ID_FUNCIONARIO'] === $fid && (int) $r['ID_EQUIPE'] === $eid);
                    if (!$ind && !isset($regraGeralUsada[$fid])) {
                        $ind = $vigente($regrasF, $dia, fn($r) => (int) $r['ID_FUNCIONARIO'] === $fid && empty($r['ID_EQUIPE']));
                        if ($ind) $regraGeralUsada[$fid] = true;
                    }
                    if ($ind) {
                        $valor = ((float) $ind['VLR_FIXO'] + $tot['m3'] * (float) $ind['VLR_M3']) * $x['peso'];
                        $linhas[] = self::linha($dia, $eq, $x['m'], $tot, $valorEquipe, 'REGRA', $x['peso'], $valor);
                    } else {
                        $rateio[] = $x;
                        $somaPesos += $x['peso'];
                    }
                }
                foreach ($rateio as $x) {
                    $valor = $somaPesos > 0 ? $valorEquipe * $x['peso'] / $somaPesos : 0.0;
                    $linhas[] = self::linha($dia, $eq, $x['m'], $tot, $valorEquipe, 'RATEIO', $x['peso'], $valor);
                }
            }
        }

        $porFunc = [];
        foreach ($linhas as $l) {
            $f = &$porFunc[$l['ID_FUNCIONARIO']];
            $f ??= ['ID_FUNCIONARIO' => $l['ID_FUNCIONARIO'], 'COD_FUNC' => $l['COD_FUNC'], 'FUNCIONARIO' => $l['FUNCIONARIO'], 'EQUIPES' => [], 'DIAS' => [], 'M3' => 0.0, 'VALOR' => 0.0];
            $f['EQUIPES'][$l['EQUIPE']] = true;
            if (!isset($f['DIAS'][$l['DATA']])) { $f['DIAS'][$l['DATA']] = true; $f['M3'] += $l['M3_DIA']; }
            $f['VALOR'] += $l['VALOR'];
            unset($f);
        }
        $resumoFunc = [];
        foreach ($porFunc as $f) {
            $resumoFunc[] = [
                'ID_FUNCIONARIO' => $f['ID_FUNCIONARIO'], 'COD_FUNC' => $f['COD_FUNC'], 'FUNCIONARIO' => $f['FUNCIONARIO'],
                'EQUIPES' => implode(' / ', array_keys($f['EQUIPES'])), 'DIAS' => count($f['DIAS']),
                'M3' => round($f['M3'], 2), 'VALOR' => round($f['VALOR'], 2),
            ];
        }
        usort($resumoFunc, fn($a, $b) => strcmp($a['FUNCIONARIO'], $b['FUNCIONARIO']));

        $m3Total = array_sum(array_column($dias, 'm3'));
        return [
            'periodo' => ['ini' => $ini, 'fim' => $fim],
            'resumo'  => [
                'dias' => count($dias), 'cargas' => array_sum(array_column($dias, 'cargas')),
                'm3' => round($m3Total, 2), 'valor' => round(array_sum(array_column($linhas, 'VALOR')), 2),
                'funcionarios' => count($resumoFunc),
            ],
            'funcionarios' => $resumoFunc,
            'detalhe'      => $linhas,
            'avisos'       => array_values($avisos),
        ];
    }

    private static function linha(string $dia, array $eq, array $m, array $tot, float $valorEquipe, string $origem, float $peso, float $valor): array
    {
        return [
            'DATA' => $dia, 'ID_EQUIPE' => (int) $eq['ID_EQUIPE'], 'EQUIPE' => $eq['NOME'], 'TIPO' => $eq['TIPO'],
            'CARGAS' => $tot['cargas'], 'M3_DIA' => round($tot['m3'], 4), 'VALOR_EQUIPE' => round($valorEquipe, 2),
            'ID_FUNCIONARIO' => (int) $m['ID_FUNCIONARIO'], 'COD_FUNC' => $m['COD_FUNC'], 'FUNCIONARIO' => $m['FUNCIONARIO'],
            'ORIGEM' => $origem, 'PESO' => $peso, 'VALOR' => round($valor, 2),
        ];
    }
}
