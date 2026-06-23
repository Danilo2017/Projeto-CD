<?php

namespace src\models\PCP;

use core\Database;

class RelatorioProd
{
    private static function executar(string $idsql, int $emprId, int $numLote): array
    {
        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'num_lote' => $numLote,
        ], $idsql, true);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }

        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function buscar(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorio.producao', $emprId, $numLote);
    }

    public static function buscarDataLote(int $emprId, int $numLote): string
    {
        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'num_lote' => $numLote,
        ], 'pcp.relatorioProd.dataLote', true);
        if (!empty($result['error'])) return '';
        $rows = is_array($result['retorno']) ? $result['retorno'] : [];
        return $rows[0]['DT'] ?? '';
    }

    public static function buscarPillow(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.pillow', $emprId, $numLote);
    }

    public static function buscarFpt(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.fpt', $emprId, $numLote);
    }

    public static function buscarMesaFaixa(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.mesaFaixa', $emprId, $numLote);
    }

    public static function buscarOptron(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.optron', $emprId, $numLote);
    }

    public static function buscarOptronAgrupado(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.optronAgrupado', $emprId, $numLote);
    }

    public static function buscarTampoLiso(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.tampoLiso', $emprId, $numLote);
    }

    public static function buscarTampoLisoAgrupado(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.tampoLisoAgrupado', $emprId, $numLote);
    }

    public static function buscarTampoBordado(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.tampoBordado', $emprId, $numLote);
    }

    public static function buscarTampoBordadoMesa(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.tampoBordadoMesa', $emprId, $numLote);
    }

    public static function buscarTampoBordadoConj(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.tampoBordadoConj', $emprId, $numLote);
    }

    public static function buscarManta(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.manta', $emprId, $numLote);
    }

    public static function buscarMantaMesa(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.mantaMesa', $emprId, $numLote);
    }

    public static function buscarMesaCorteCaixaBox(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.mesaCorteCaixaBox', $emprId, $numLote);
    }

    public static function buscarMesaCorteCaixote(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.mesaCorteCaixote', $emprId, $numLote);
    }

    public static function buscarBordadeira(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.bordadeira', $emprId, $numLote);
    }

    public static function buscarTapecaria(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.tapecaria', $emprId, $numLote);
    }

    public static function buscarConjugado(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.conjugado', $emprId, $numLote);
    }

    public static function buscarTravePeze(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.travePeze', $emprId, $numLote);
    }

    public static function buscarMolasBordas(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.molasBordas', $emprId, $numLote);
    }

    public static function buscarCaixaBoxPrincipal(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.caixaBoxPrincipal', $emprId, $numLote);
    }

    public static function buscarCaixaBoxAux(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.caixaBoxAux', $emprId, $numLote);
    }

    public static function buscarRobotec(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.robotec', $emprId, $numLote);
    }

    public static function buscarRoloBordado(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.roloBordado', $emprId, $numLote);
    }

    public static function buscarRobotecAbastecedor(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.robotecAbastecedor', $emprId, $numLote);
    }

    public static function buscarRoloBordadoDetalhe(int $emprId, int $numLote): array
    {
        return self::executar('pcp.relatorioProd.roloBordadoDetalhe', $emprId, $numLote);
    }

    public static function buscarVerticalEspuma(int $emprId, int $numLote): array
    {
        $sql = "SELECT TABLES.ORD                       ORD,
       TORDENS.NUM_LOTE_PRO               LOTE,
       TORDENS.NUM_ORDEM                  NUM_ORDEM,
       TABLES.DESC_TECNICA                DESCRICAO,
       TABLES.MASCARA                     MASCARA,
       TABLES.QTDE_OF                     QTDE,
       TITENS.COD_ITEM                    COD_ITEM,
       TITENS.DESC_TECNICA                DESC_TECNICA,
       TMASC_ITEM.MASCARA                 MASCARA_ITEM
  FROM TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TORDENS TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(PI_EMPR_ID=>TORDENS.EMPR_ID,PI_LOTE=>TORDENS.NUM_LOTE_PRO,PI_ORDEM_ID=>TORDENS.ID,PI_ORDEM=>ROWNUM)) TABLES,
       TDEMANDAS TDEMANDAS,
       TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID             = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TDEMANDAS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID        = :empr_id
   AND TORDENS.NUM_LOTE_PRO   = :num_lote
   AND TITENS.DESC_TECNICA    LIKE 'MANTA%'
   AND NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 9999) < 1000
   AND NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 0)    > 100
ORDER BY TABLES.DESC_TECNICA ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 9999) ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 9999) ASC,
         TABLES.ORDEM ASC";

        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'num_lote' => $numLote,
        ], null, true, true, null, $sql);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function buscarHorizontalEspuma(int $emprId, int $numLote): array
    {
        $sql = "SELECT TABLES.ORD                       ORD,
       TORDENS.NUM_LOTE_PRO               LOTE,
       TORDENS.NUM_ORDEM                  NUM_ORDEM,
       TABLES.DESC_TECNICA                DESCRICAO,
       TABLES.MASCARA                     MASCARA,
       TABLES.QTDE_OF                     QTDE,
       TITENS.COD_ITEM                    COD_ITEM,
       TITENS.DESC_TECNICA                DESC_TECNICA,
       TMASC_ITEM.MASCARA                 MASCARA_ITEM
  FROM TITENS_PLANEJAMENTO TITENS_PLANEJAMENTO,
       TORDENS TORDENS,
       TABLE(GAZIN_UTIL_RRP.GAZIN_COLCHOES_ESPECIAIS(PI_EMPR_ID=>TORDENS.EMPR_ID,PI_LOTE=>TORDENS.NUM_LOTE_PRO,PI_ORDEM_ID=>TORDENS.ID,PI_ORDEM=>ROWNUM)) TABLES,
       TDEMANDAS TDEMANDAS,
       TITENS_EMPR TITENS_EMPR,
       TITENS TITENS,
       TMASC_ITEM TMASC_ITEM
 WHERE TITENS_PLANEJAMENTO.ID = TDEMANDAS.ITPL_ID
   AND TORDENS.ID             = TDEMANDAS.ORDEM_ID
   AND TITENS_EMPR.ID         = TITENS_PLANEJAMENTO.ITEMPR_ID
   AND TITENS.ID              = TITENS_EMPR.ITEM_ID
   AND TMASC_ITEM.ID(+)       = TDEMANDAS.TMASC_ITEM_ID
   AND TORDENS.EMPR_ID        = :empr_id
   AND TORDENS.NUM_LOTE_PRO   = :num_lote
   AND TITENS.DESC_TECNICA    LIKE 'MANTA%'
   AND (  NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 0)    >= 1000
       OR NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 9999) <= 100 )
ORDER BY NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 2)), 9999) ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 3)), 9999) ASC,
         NVL(TO_NUMBER(REGEXP_SUBSTR(TMASC_ITEM.MASCARA, '[^#]+', 1, 1)), 9999) ASC,
         TABLES.ORDEM ASC";

        $result = Database::switchParams('focco', [
            'empr_id'  => $emprId,
            'num_lote' => $numLote,
        ], null, true, true, null, $sql);

        if (!empty($result['error'])) {
            throw new \Exception($result['error']);
        }
        return is_array($result['retorno']) ? $result['retorno'] : [];
    }

    public static function listarEmpresas(): array
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }
}
