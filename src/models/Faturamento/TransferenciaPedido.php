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
        $pdo = Database::getInstance('focco');

        $filtroNumeros = '';
        if (!empty($numeros)) {
            $inClause      = implode(',', array_map('intval', $numeros));
            $filtroNumeros = "AND TV.NUM_PEDIDO IN ($inClause)";
        }

        $sql = "SELECT TV.ID,
                       TV.EMPR_ID                AS EMPRESA,
                       TV.NUM_PEDIDO,
                       T.COD_CLI                 AS COD_CLIENTE,
                       T.DESCRICAO               AS NOME_CLIENTE,
                       TN.COD_TP_NF              AS COD_TP_NF,
                       TN.DESCRICAO              AS DESCRICAO_NF,
                       TV2.COD_DIVD              AS COD_DIVISAO,
                       TV2.DESCRICAO             AS DESCRICAO_DIVISAO,
                       TV.VLR_LIQ
                FROM FOCCO3I.TPEDIDOS_VENDA    TV,
                     FOCCO3I.TTIPOS_NF         TN,
                     FOCCO3I.TITENS_PDV        TP,
                     FOCCO3I.TDIVISOES_VENDAS  TV2,
                     FOCCO3I.TCLIENTES         T
                WHERE TV2.ID      = TV.DIVD_ID
                AND   TP.TPNF_ID  = TN.ID
                AND   T.ID        = TV.CLI_ID
                AND   TV.ID       = TP.PDV_ID
                AND   TV.SIT_PDV  = 'LIB'
                AND   TV.POS_PDV  = 'PE'
                AND   TV.TIPO     = 'PDV'
                AND   TV.EMPR_ID  = $emprId
                $filtroNumeros
                GROUP BY TV.ID,
                         TV.EMPR_ID,
                         TV.NUM_PEDIDO,
                         T.COD_CLI,
                         T.DESCRICAO,
                         TN.COD_TP_NF,
                         TN.DESCRICAO,
                         TV2.COD_DIVD,
                         TV2.DESCRICAO,
                         TV.VLR_LIQ
                ORDER BY TV.NUM_PEDIDO";

        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Executa a transferência de um pedido via GAZIN_CRIA_PEDIDO_VENDA.
     * Retorna ['sucesso', 'erro', 'num_pedido_dest'].
     */
    public static function executarTransferencia(int $pdvId, int $emprDestId, int $codTpNf, int $codPreven): array
    {
        try {
            $pdo = Database::getInstance('focco');

            // Grava o maior ID atual da filial destino antes de executar
            $stmtAntes = $pdo->query(
                "SELECT NVL(MAX(ID), 0) AS MAX_ID FROM FOCCO3I.TPEDIDOS_VENDA WHERE EMPR_ID = $emprDestId"
            );
            $maxIdAntes = (int) ($stmtAntes->fetch(\PDO::FETCH_ASSOC)['MAX_ID'] ?? 0);

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

            // Busca pedido criado com ID maior que o registrado antes da procedure
            $stmtNovo = $pdo->query(
                "SELECT NUM_PEDIDO, ID FROM FOCCO3I.TPEDIDOS_VENDA
                 WHERE EMPR_ID = $emprDestId
                 AND ID > $maxIdAntes
                 ORDER BY ID DESC FETCH FIRST 1 ROW ONLY"
            );
            $novo = $stmtNovo->fetch(\PDO::FETCH_ASSOC);

            if (!$novo) {
                return [
                    'sucesso'         => false,
                    'erro'            => 'Procedure executou sem erros mas nenhum pedido foi gerado na filial destino.',
                    'num_pedido_dest' => null,
                    'pdv_id_dest'     => null,
                ];
            }

            $novoId = (int) $novo['ID'];

            // Busca o NUM_PEDIDO do pedido de origem
            $stmtOrig = $pdo->query("SELECT NUM_PEDIDO FROM FOCCO3I.TPEDIDOS_VENDA WHERE ID = $pdvId");
            $orig = $stmtOrig->fetch(\PDO::FETCH_ASSOC);
            $numPedOrig = (int) ($orig['NUM_PEDIDO'] ?? 0);

            // Grava o número do pedido de origem no pedido gerado
            if ($numPedOrig > 0) {
                $pdo->exec("UPDATE FOCCO3I.TPEDIDOS_VENDA SET NUM_PED_ORIGEM = $numPedOrig WHERE ID = $novoId");
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
        // Extrai só o texto entre ORA-20001: e o próximo ORA-XXXXX: ou fim
        if (preg_match('/ORA-20001:\s*(.+?)(?:\s*ORA-\d+:|$)/s', $msg, $m)) {
            return trim($m[1]);
        }
        // Fallback: remove prefixos técnicos
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
