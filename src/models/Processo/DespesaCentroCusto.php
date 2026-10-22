<?php

namespace src\models\Processo;

use core\Database;

class DespesaCentroCusto
{
    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    private static function exec(string $sql): array
    {
        $res = Database::switchParams('focco', [], null, true, false, null, $sql);
        if (!empty($res['error'])) throw new \Exception($res['error']);
        return is_array($res['retorno']) ? $res['retorno'] : [];
    }

    /** Converte "19014, 19015" em "19014,19015" (somente inteiros). */
    private static function listaInt(string $v): string
    {
        $nums = array_filter(array_map('intval', preg_split('/[^0-9]+/', $v)), fn($n) => $n > 0);
        return implode(',', array_unique($nums));
    }

    /** Um lado da partida: 'DEB' ou 'CRE'. */
    private static function lado(string $lado, string $filtros): string
    {
        $sinal = $lado === 'DEB' ? '' : ' * -1';
        $nat   = $lado === 'DEB' ? 'D' : 'C';
        $vlr   = 'NVL(TLCTO_CTAB_CC.VLR_LCTO, TLCTO_CTAB.VLR_LCTO)';

        return "SELECT TEMPRESAS.COD_EMP                 COD_EMP,
                       TCTA_CTAB.CTA                     CTA,
                       TCTA_CTAB.DESCRICAO               DESC_CONTA,
                       TEMP_CTA_CTAB.COD_RED             COD_RED,
                       TEMP_CC.COD                       COD_CC,
                       TCC.DESCRICAO                     DESC_CC,
                       TLCTO_CTAB.ID                     LCTO_ID,
                       TLCTO_CTAB.LCTO                   LCTO,
                       TO_CHAR(TLCTO_CTAB.DATA, 'DD/MM/YYYY') DATA,
                       TO_CHAR(TLCTO_CTAB.DATA, 'YYYYMMDD')   DATA_ORD,
                       TLCTO_CTAB.TP_LCTO                TP_LCTO,
                       TLCTO_CTAB.COMPL                  HISTORICO,
                       '$nat'                            NATUREZA,
                       $vlr$sinal                        VALOR,
                       CASE TCTA_CTAB.CUSTO_FIXO WHEN 1 THEN 'SIM' ELSE 'NAO' END CUSTO_FIXO
                  FROM TCTA_CTAB, TLCTO_CTAB_CC, TLCTO_CTAB, TEMP_CTA_CTAB, TEMP_CC, TEMPRESAS, TCC
                 WHERE TCTA_CTAB.ID(+)         = TEMP_CTA_CTAB.CTA_CTAB_ID
                   AND TLCTO_CTAB.ID           = TLCTO_CTAB_CC.LCTO_CTAB_ID(+)
                   AND TEMP_CTA_CTAB.ID        = TLCTO_CTAB.EMP_CTA_ID_$lado
                   AND TEMP_CC.ID(+)           = TLCTO_CTAB_CC.EMP_CC_ID_$lado
                   AND TEMP_CTA_CTAB.EMPR_ID   = TEMPRESAS.ID(+)
                   AND TEMP_CC.CC_ID           = TCC.ID(+)
                   AND TLCTO_CTAB.TP_LCTO NOT LIKE 'TRA'
                   $filtros";
    }

    public static function listar(string $dtIni, string $dtFim, int $codEmp, string $cc, string $grupoConta, string $codRed): array
    {
        $di = "TO_DATE('$dtIni','YYYY-MM-DD')";
        $df = "TO_DATE('$dtFim','YYYY-MM-DD')";

        $filtros = "AND TEMPRESAS.COD_EMP = $codEmp
                    AND TLCTO_CTAB.DATA >= $di
                    AND TLCTO_CTAB.DATA <  $df + 1";

        $ccs = self::listaInt($cc);
        if ($ccs !== '') $filtros .= " AND TEMP_CC.COD IN ($ccs)";

        $grupoConta = preg_replace('/[^0-9]/', '', $grupoConta);
        if ($grupoConta !== '') $filtros .= " AND TCTA_CTAB.CTA LIKE '$grupoConta%'";

        $reds = self::listaInt($codRed);
        if ($reds !== '') $filtros .= " AND TEMP_CTA_CTAB.COD_RED IN ($reds)";

        $sql = "SELECT * FROM (" . self::lado('DEB', $filtros) . "
                UNION ALL " . self::lado('CRE', $filtros) . ")
                ORDER BY COD_CC, CTA, DATA_ORD, LCTO";

        return self::exec($sql);
    }

    /** Notas fiscais de entrada (e seus itens) vinculadas a um lançamento contábil. */
    public static function detalhe(int $lctoId): array
    {
        $sql = "SELECT TNFS_ENTRADA.ID                        NFE_ID,
                       TNFS_ENTRADA.NUM_NF                    NUM_NF,
                       TNFS_ENTRADA.SERIE                     SERIE,
                       TO_CHAR(TNFS_ENTRADA.DT_EMIS, 'DD/MM/YYYY') DT_EMIS,
                       TO_CHAR(TNFS_ENTRADA.DT_ENT,  'DD/MM/YYYY') DT_ENT,
                       TNFS_ENTRADA.NOME                      FORNECEDOR,
                       TNFS_ENTRADA.CNPJ_CPF                  CNPJ_CPF,
                       TITENS_NFE.ID                          ITNFE_ID,
                       TITENS_NFE.NUM_ITE                     NUM_ITE,
                       TITENS_SUPRIMENTOS.COD_ITEM            COD_ITEM,
                       NVL(TITENS.DESC_TECNICA, TITENS_NFE.DESCRICAO) DESC_ITEM,
                       TMASC_ITEM.MASCARA                     MASCARA,
                       TUNID_MED.COD_UNID_MED                 UNID,
                       TITENS_NFE.QTDE                        QTDE,
                       TITENS_NFE.PRECO_UNIT                  PRECO_UNIT,
                       TITENS_NFE.VLR_TOTAL                   VALOR
                  FROM TLCTO_INT_CTAB, TITENS_NFE, TNFS_ENTRADA, TITENS_SUPRIMENTOS,
                       TITENS_EMPR, TITENS, TMASC_ITEM, TUNID_MED
                 WHERE TLCTO_INT_CTAB.LCTO_CTAB_ID = $lctoId
                   AND TLCTO_INT_CTAB.TIPO         = 'MOV'
                   AND TITENS_NFE.ID               = TLCTO_INT_CTAB.ITNFE_ID
                   AND TNFS_ENTRADA.ID             = TITENS_NFE.NFE_ID
                   AND TITENS_SUPRIMENTOS.ID(+)    = TITENS_NFE.ITES_ID
                   AND TITENS_EMPR.ID(+)           = TITENS_SUPRIMENTOS.ITEMPR_ID
                   AND TITENS.ID(+)                = TITENS_EMPR.ITEM_ID
                   AND TMASC_ITEM.ID(+)            = TITENS_NFE.TMASC_ITEM_ID
                   AND TUNID_MED.ID(+)             = TITENS_NFE.UNID_MED_ID
                 ORDER BY TNFS_ENTRADA.NUM_NF, TITENS_NFE.NUM_ITE";

        return self::exec($sql);
    }
}
