<?php

namespace src\handlers\CD;

/**
 * Handler dos Indicadores Mensais de Recebimento CD
 *
 * Reaproveita a listagem do calendário (agendamentos + recibos) e agrega
 * em PHP, considerando apenas agendamentos recebidos (status FINALIZADO).
 */
class CDIndicadoresHandler
{
    const TIPO_TECIDOS  = 'Tecidos';
    const TIPO_DIVERSOS = 'Diversos';

    /**
     * Monta os indicadores de um mês
     *
     * @param int $ano
     * @param int $mes 1-12
     * @return array resumo, por_tipo e diario
     */
    public static function indicadoresMes(int $ano, int $mes): array
    {
        $prefixo = sprintf('%04d-%02d', $ano, $mes);
        $diasNoMes = (int)date('t', mktime(0, 0, 0, $mes, 1, $ano));

        $diario = [];
        for ($d = 1; $d <= $diasNoMes; $d++) {
            $diario[$d] = self::linhaVazia(['dia' => sprintf('%02d/%02d', $d, $mes)]);
        }

        $porTipo = [
            self::TIPO_TECIDOS  => self::linhaVazia(['tipo' => self::TIPO_TECIDOS]),
            self::TIPO_DIVERSOS => self::linhaVazia(['tipo' => self::TIPO_DIVERSOS]),
        ];

        $total = self::linhaVazia();

        foreach (CDCalendarioHandler::listarRecebimentos() as $rec) {
            if (empty($rec['recebido']) || strpos((string)$rec['data'], $prefixo) !== 0) {
                continue;
            }

            $dia = (int)substr($rec['data'], 8, 2);
            $tipo = self::classificarTipo($rec['descricao'] ?? '');

            self::somar($diario[$dia], $rec);
            self::somar($porTipo[$tipo], $rec);
            self::somar($total, $rec);
        }

        foreach ($porTipo as &$linha) {
            $linha['percentual'] = $total['valor'] > 0 ? round($linha['valor'] / $total['valor'] * 100, 1) : 0;
        }
        unset($linha);

        return [
            'mes'      => $prefixo,
            'resumo'   => $total,
            'por_tipo' => array_values($porTipo),
            'diario'   => array_values($diario),
        ];
    }

    /**
     * Tecidos quando a descrição menciona "tecido"; o resto é Diversos
     */
    private static function classificarTipo(string $descricao): string
    {
        return stripos($descricao, 'tecido') !== false ? self::TIPO_TECIDOS : self::TIPO_DIVERSOS;
    }

    private static function linhaVazia(array $extra = []): array
    {
        return $extra + ['veiculos' => 0, 'peso_ton' => 0.0, 'volume' => 0.0, 'valor' => 0.0];
    }

    /**
     * Peso é cadastrado em KG; indicador exibe em toneladas
     */
    private static function somar(array &$linha, array $rec): void
    {
        $linha['veiculos']++;
        $linha['peso_ton'] += (float)($rec['peso'] ?? 0) / 1000;
        $linha['volume']   += (float)($rec['volume'] ?? 0);
        $linha['valor']    += (float)($rec['valor_pago'] ?? 0);
    }
}
