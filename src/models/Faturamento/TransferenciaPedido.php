<?php

namespace src\models\Faturamento;

use core\Database;

class TransferenciaPedido
{
    /**
     * Busca pedidos liberados (SIT_PDV=LIB, POS_PDV=PE, TIPO=PDV) da filial de origem.
     */
    public static function buscarPedidos(int $emprId, array $numeros = []): array
    {
        $filtroNumeros = '';
        if (!empty($numeros)) {
            $inClause      = implode(',', array_map('intval', $numeros));
            $filtroNumeros = "AND TV.NUM_PEDIDO IN ($inClause)";
        }

        $result = Database::switchParams('focco', [
            'empr_id'         => $emprId,
            'filtro_numeros'  => $filtroNumeros ?: '--',
        ], 'faturamento.transferencia.buscarPedidos', true);

        return $result['retorno'] ?? [];
    }

    /**
     * Executa a transferência de um pedido via GAZIN_CRIA_PEDIDO_VENDA.
     * Retorna ['sucesso', 'erro', 'num_pedido_dest'].
     */
    public static function executarTransferencia(int $pdvId, int $emprDestId, int $codTpNf, int $codPreven): array
    {
        try {
            $pdo = Database::getInstance('focco');

            $resMax     = Database::switchParams('focco', ['empr_id' => $emprDestId], 'faturamento.transferencia.maxIdDest', true, false);
            $maxIdAntes = (int) ($resMax['retorno'][0]['MAX_ID'] ?? 0);

            $block = "DECLARE
                v_saida  VARCHAR2(32767) := '';
                v_linha  VARCHAR2(32767);
                v_status INTEGER;
                v_erro   VARCHAR2(4000)  := NULL;
            BEGIN
                DBMS_OUTPUT.ENABLE(1000000);
                FOCCO3I.FOCCO3I_PARAMETROS.SET_PARAMETRO('PI_PDV_ID_ORIG',  $pdvId);
                FOCCO3I.FOCCO3I_PARAMETROS.SET_PARAMETRO('PI_EMPR_ID_DEST', $emprDestId);
                FOCCO3I.FOCCO3I_PARAMETROS.SET_PARAMETRO('PI_COD_TP_NF',    $codTpNf);
                FOCCO3I.FOCCO3I_PARAMETROS.SET_PARAMETRO('PI_COD_PREVEN',   $codPreven);
                GAZIN_CRIA_PEDIDO_VENDA;

                -- Tenta GET_PARAMETRO como função via EXECUTE IMMEDIATE (evita erro de compilação)
                BEGIN
                    EXECUTE IMMEDIATE
                        'BEGIN :r := FOCCO3I.FOCCO3I_PARAMETROS.GET_PARAMETRO(''PO_ERRO''); END;'
                        USING OUT v_erro;
                EXCEPTION WHEN OTHERS THEN
                    -- Tenta como procedure com parâmetro OUT
                    BEGIN
                        EXECUTE IMMEDIATE
                            'BEGIN FOCCO3I.FOCCO3I_PARAMETROS.GET_PARAMETRO(''PO_ERRO'', :r); END;'
                            USING OUT v_erro;
                    EXCEPTION WHEN OTHERS THEN
                        v_erro := NULL;
                    END;
                END;

                -- Lê todo o DBMS_OUTPUT
                LOOP
                    DBMS_OUTPUT.GET_LINE(v_linha, v_status);
                    EXIT WHEN v_status != 0;
                    v_saida := v_saida || v_linha || CHR(10);
                END LOOP;

                -- Prioridade 1: erro via FOCCO3I_PARAMETROS
                IF v_erro IS NOT NULL AND LENGTH(TRIM(v_erro)) > 0 THEN
                    ROLLBACK;
                    RAISE_APPLICATION_ERROR(-20001, SUBSTR(TRIM(v_erro), 1, 2000));
                END IF;

                -- Prioridade 2: erro via DBMS_OUTPUT
                IF INSTR(UPPER(v_saida), 'ERRO') > 0 THEN
                    ROLLBACK;
                    RAISE_APPLICATION_ERROR(-20001, SUBSTR(TRIM(v_saida), 1, 2000));
                END IF;

                COMMIT;
            END;";

            $pdo->exec($block);

            $resNovo = Database::switchParams('focco', [
                'empr_id' => $emprDestId,
                'max_id'  => $maxIdAntes,
            ], 'faturamento.transferencia.buscarPedidoGerado', true, false);
            $novo = $resNovo['retorno'][0] ?? null;

            if (!$novo) {
                return [
                    'sucesso'         => false,
                    'erro'            => 'Procedure executou sem erros mas nenhum pedido foi gerado na filial destino.',
                    'num_pedido_dest' => null,
                    'pdv_id_dest'     => null,
                ];
            }

            $novoId = (int) $novo['ID'];

            $resOrig    = Database::switchParams('focco', ['id' => $pdvId], 'faturamento.transferencia.buscarNumPedOrigem', true, false);
            $orig       = $resOrig['retorno'][0] ?? null;
            $numPedOrig = (int) ($orig['NUM_PEDIDO'] ?? 0);

            if ($numPedOrig > 0) {
                Database::switchParams('focco', [
                    'num_ped_origem' => $numPedOrig,
                    'id'             => $novoId,
                ], 'faturamento.transferencia.gravarNumPedOrigem', true, true);
                $pdo->exec("COMMIT");
            }

            return [
                'sucesso'         => true,
                'erro'            => null,
                'num_pedido_dest' => $novo['NUM_PEDIDO'],
                'pdv_id_dest'     => $novoId,
            ];
        } catch (\Exception $e) {
            return ['sucesso' => false, 'erro' => self::limparErro($e->getMessage()), 'num_pedido_dest' => null, 'pdv_id_dest' => null];
        }
    }

    private static function limparErro(string $msg): string
    {
        if (preg_match('/ORA-20001:\s*(.+?)(?:\s*ORA-\d+:|$)/s', $msg, $m)) {
            return trim($m[1]);
        }
        $msg = preg_replace('/^SQLSTATE\[.*?\]:\s*General error:\s*\d+\s*\S+:\s*/s', '', $msg);
        $msg = preg_replace('/\s*ORA-06512:.*$/s', '', $msg);
        $msg = preg_replace('/^ORA-\d+:\s*/', '', $msg);
        return trim($msg);
    }

    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }
}
