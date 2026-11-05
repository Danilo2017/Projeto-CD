-- =====================================================================
-- Pacote GAZIN_PMF — Consulta Planej. Material por Filial (painel Planejamento)
-- Executar como FOCCO3I (depois dos scripts 01 e 02). Separador: linha com "/".
--
-- Mesma regra da OTIMIZA.PPLA_MAT_FILIAL_OTM (validado: resultado idêntico, linha a linha),
-- sem as subconsultas repetidas por linha:
--  * perfil do MRP (DD / interfábrica OCI+OIF+OCL) agregado uma vez para todos os itens,
--    em vez de F3IMOV_DADOS_PERFIL_ITEM_GAZIN várias vezes por linha;
--  * primeiro pedido (PDV na FL 1, PDC nas demais) calculado uma vez (KEEP DENSE_RANK FIRST);
--  * saídas de 90 e 30 dias numa leitura só; almox. padrão/outros calculados uma vez por filial.
-- =====================================================================
CREATE OR REPLACE PACKAGE GAZIN_PMF AS
    -- Gera a consulta para o usuário (apaga a geração anterior dele) e devolve a SESSAO.
    PROCEDURE GERAR (pi_usuario  IN  VARCHAR2,
                     pi_empresas IN  VARCHAR2,   -- ex.: '1,3,5' (TEMPRESAS.ID)
                     pi_dt_ini   IN  DATE,
                     pi_dt_fim   IN  DATE,
                     po_sessao   OUT NUMBER);
END GAZIN_PMF;
/

CREATE OR REPLACE PACKAGE BODY GAZIN_PMF AS

    c_sql CONSTANT VARCHAR2(32767) := q'~
WITH
emp AS (SELECT e.id empr_id, FOCCO3I.GAZIN_PMF_ALMOX_LISTA(e.id, 1) almox_pad, FOCCO3I.GAZIN_PMF_ALMOX_LISTA(e.id, 2) almox_out
          FROM focco3i.tempresas e WHERE e.id IN ({EMPRESAS})),
base AS (SELECT /*+ MATERIALIZE */ * FROM (
    -- itens sem máscara
    SELECT t.id item_id, t.cod_item, t.desc_tecnica desc_item, te.id item_empr_id, te.empr_id, g.cod_grp_ite,
           CAST(NULL AS NUMBER) masc_id, CAST(NULL AS VARCHAR2(4000)) mascara,
           tp.lote_mult, tp.estq_seg, tp.lote_min, tp.tempo_rep, NVL(tp.cons_medio,0) / 22 dias_est,
           eng.tipo_produto, NVL(eng.peso_brt,0) peso, tp.id planejamento_id, gi.cod_grp_invent
      FROM focco3i.titens t, focco3i.titens_empr te, focco3i.titens_engenharia eng, focco3i.tgrp_clas_ite g,
           focco3i.titens_planejamento tp, focco3i.titens_estoque es, focco3i.talmoxarifados al,
           focco3i.titens_contabil tc, focco3i.tgrp_invent gi
     WHERE t.id = te.item_id AND te.id = eng.itempr_id AND te.id = tp.itempr_id AND g.id = eng.grp_clas_id
       AND te.id = es.itempr_id AND al.id = es.almox_id AND te.id = tc.itempr_id AND gi.id = tc.grp_invent_id
       AND EXISTS (SELECT 1 FROM focco3i.titens_empr e1, focco3i.titens_estoque s1, focco3i.talmoxarifados a1
                    WHERE e1.empr_id = 1 AND e1.item_id = t.id AND e1.id = s1.itempr_id AND a1.id = s1.almox_id AND a1.cod_almox = 1)
       AND eng.sit = 1 AND t.sit = 1 AND te.sit = 1 AND t.configurado = 0
       AND NOT EXISTS (SELECT 1 FROM focco3i.titens_estoque_esp x WHERE x.especial_id = es.id AND x.crossdocking = 1)
       AND te.empr_id IN ({EMPRESAS}) AND g.cod_grp_ite LIKE '10.%'
    UNION ALL
    -- itens configurados (uma linha por máscara)
    SELECT t.id, t.cod_item, t.desc_tecnica, te.id, te.empr_id, g.cod_grp_ite,
           m.id, m.mascara,
           pc.lote_mult, pc.estq_seg, pc.lote_min, pc.tempo_rep, NVL(pc.cons_medio,0) / 22,
           ec.tipo_produto, NVL(ec.peso_brt,0), tp.id, gi.cod_grp_invent
      FROM focco3i.titens t, focco3i.titens_empr te, focco3i.titens_engenharia eng, focco3i.tgrp_clas_ite g,
           focco3i.titens_eng_conf ec, focco3i.tmasc_item m, focco3i.titens_planejamento tp, focco3i.titens_plan_conf pc,
           focco3i.titens_estoque es, focco3i.titens_estq_conf eqc, focco3i.talmoxarifados al,
           focco3i.titens_contabil tc, focco3i.tgrp_invent gi
     WHERE t.id = te.item_id AND te.id = eng.itempr_id AND eng.id = ec.iteg_id AND g.id = eng.grp_clas_id
       AND m.id = ec.tmasc_item_id AND te.id = tp.itempr_id AND tp.id = pc.itpl_id AND m.id = pc.tmasc_item_id
       AND te.id = es.itempr_id AND es.id = eqc.itestq_id AND m.id = eqc.tmasc_item_id AND al.id = eqc.almox_id
       AND te.id = tc.itempr_id AND gi.id = tc.grp_invent_id
       AND EXISTS (SELECT 1 FROM focco3i.titens_empr e1, focco3i.titens_estoque s1, focco3i.talmoxarifados a1
                    WHERE e1.empr_id = 1 AND e1.item_id = t.id AND e1.id = s1.itempr_id AND a1.id = s1.almox_id AND a1.cod_almox = 1)
       AND eng.sit = 1 AND t.sit = 1 AND te.sit = 1 AND t.configurado = 1 AND m.sit = 1
       AND NOT EXISTS (SELECT 1 FROM focco3i.titens_estoque_esp x WHERE x.especial_id = es.id AND x.crossdocking = 1)
       AND te.empr_id IN ({EMPRESAS}) AND g.cod_grp_ite LIKE '10.%'
)),
-- saídas de estoque da própria filial: 90 e 30 dias numa leitura só (por item da filial e máscara)
mov AS (
    SELECT emp.id item_empr_id, mov.tmasc_item_id masc_id,
           SUM(mov.qtde) q90,
           SUM(CASE WHEN mov.dt >= TRUNC(SYSDATE) - 30 THEN mov.qtde END) q30
      FROM focco3i.tmov_estq mov, focco3i.titens_estoque itest, focco3i.titens_empr emp,
           focco3i.tgrp_clas_ite grp, focco3i.ttp_mov_estq tmov
     WHERE mov.itestq_id = itest.id AND itest.grp_clas_id = grp.id AND mov.tmves_id = tmov.id
       AND mov.ent_sai = 'S' AND mov.dt BETWEEN TRUNC(SYSDATE) - 90 AND TRUNC(SYSDATE)
       AND grp.cod_grp_ite LIKE '10%' AND tmov.sigla IN ('REP','REQ','NFS','RSI')
       AND itest.itempr_id = emp.id AND mov.empr_id IN ({EMPRESAS})
       AND emp.id IN (SELECT item_empr_id FROM base)
     GROUP BY emp.id, mov.tmasc_item_id
),
mov_sm AS (SELECT item_empr_id, SUM(q90) q90, SUM(q30) q30 FROM mov GROUP BY item_empr_id),
-- perfil do MRP (plano cód. 1): DD = demanda; OCI/OIF/OCL = interfábrica — janela [ini, fim]
perf AS (
    SELECT p.itpl_id, NVL(p.tmasc_item_id, 1) mk,
           SUM(CASE WHEN p.tipo_ord_dem = 'DD' THEN p.qtde END) dd,
           SUM(CASE WHEN p.tipo_ord_dem IN ('OCI','OIF','OCL') THEN p.qtde END) itf
      FROM focco3i.tperfil_itens p, focco3i.tplanos pl
     WHERE pl.id = p.plano_id AND pl.cod_plano = 1 AND p.tipo_ord_dem IN ('DD','OCI','OIF','OCL')
       AND p.dt_fim >= :ini1 AND p.dt_fim < :fim1 + 1
       AND p.itpl_id IN (SELECT planejamento_id FROM base)
     GROUP BY p.itpl_id, NVL(p.tmasc_item_id, 1)
),
-- FL 1: pedidos de venda (cliente 1 / estab. 1) com saldo
pdv AS (
    SELECT ic.itempr_id, ip.tmasc_item_id, pv.id pdv_id, pv.dt_entrega, pv.num_pedido, pv.dt_emis
      FROM focco3i.tpedidos_venda pv, focco3i.titens_pdv ip, focco3i.titens_comercial ic,
           focco3i.tclientes cl, focco3i.testabelecimentos es
     WHERE pv.id = ip.pdv_id AND ic.id = ip.itcm_id AND cl.id = pv.cli_id AND es.id = pv.est_id_fat
       AND cl.cod_cli = 1 AND es.cod_est = 1 AND pv.empr_id = 1 AND pv.tipo = 'PDV' AND ip.qtde_sldo > 0
       AND ic.itempr_id IN (SELECT item_empr_id FROM base WHERE empr_id = 1)
),
pdv_sm AS (SELECT itempr_id, MIN(pdv_id) KEEP (DENSE_RANK FIRST ORDER BY dt_entrega) pdv_id FROM pdv GROUP BY itempr_id),
pdv_cm AS (SELECT itempr_id, tmasc_item_id, MIN(pdv_id) KEEP (DENSE_RANK FIRST ORDER BY dt_entrega) pdv_id
             FROM pdv WHERE tmasc_item_id IS NOT NULL GROUP BY itempr_id, tmasc_item_id),
-- demais filiais: pedidos de compra pendentes da própria filial
pdc AS (
    SELECT pc.empr_id, te.item_id, pi.masc_item_id, pc.id pedc_id, pi.dt_entrega
      FROM focco3i.tped_compra pc, focco3i.tpedc_item pi, focco3i.titens_suprimentos ts, focco3i.titens_empr te
     WHERE pc.id = pi.tpedc_id AND ts.id = pi.item_id AND te.id = ts.itempr_id
       AND pi.qtde_saldo > 0 AND pc.posicao = 'P'
       AND pc.empr_id IN ({EMPRESAS}) AND pc.empr_id <> 1
       AND te.item_id IN (SELECT item_id FROM base)
),
pdc_sm AS (SELECT empr_id, item_id, MIN(dt_entrega) dt_ent, MIN(pedc_id) KEEP (DENSE_RANK FIRST ORDER BY dt_entrega) pedc_id
             FROM pdc GROUP BY empr_id, item_id),
pdc_cm AS (SELECT empr_id, item_id, masc_item_id, MIN(dt_entrega) dt_ent, MIN(pedc_id) KEEP (DENSE_RANK FIRST ORDER BY dt_entrega) pedc_id
             FROM pdc WHERE masc_item_id IS NOT NULL GROUP BY empr_id, item_id, masc_item_id),
calc AS (
    SELECT b.*,
           CASE WHEN b.masc_id IS NULL THEN ms.q90 ELSE mc.q90 END / 3                media_trimestral,
           CASE WHEN b.masc_id IS NULL THEN ms.q30 ELSE mc.q30 END                    media_mensal,
           focco3i.man_est_retorna_saldo(1, b.item_id, '1', SYSDATE, NULL,
               CASE WHEN b.masc_id IS NULL THEN NULL ELSE
                   NVL((SELECT MAX(m1.id) FROM focco3i.titens_empr e1, focco3i.titens_estoque s1,
                                               focco3i.titens_estq_conf q1, focco3i.tmasc_item m1
                         WHERE e1.id = s1.itempr_id AND s1.id = q1.itestq_id AND m1.id = q1.tmasc_item_id
                           AND e1.item_id = b.item_id AND e1.empr_id = 1 AND m1.mascara = b.mascara), 0) END) saldo_cd,
           CASE WHEN b.empr_id = 1
                THEN focco3i.man_est_retorna_saldo(b.empr_id, b.item_id, 4, SYSDATE, NULL, b.masc_id)
                ELSE focco3i.man_est_retorna_saldo(b.empr_id, b.item_id, ep.almox_pad, SYSDATE, NULL, b.masc_id)
           END almox_padrao,
           focco3i.man_est_retorna_saldo(b.empr_id, b.item_id, ep.almox_out, SYSDATE, NULL, b.masc_id) outros_almox,
           CASE WHEN b.empr_id = 1 THEN
               (SELECT SUM(ip.qtde)
                  FROM focco3i.tpedidos_venda pv, focco3i.titens_pdv ip, focco3i.titens_comercial ic,
                       focco3i.tclientes cl, focco3i.testabelecimentos es
                 WHERE pv.id = ip.pdv_id AND ic.id = ip.itcm_id AND cl.id = pv.cli_id AND es.id = pv.est_id_fat
                   AND cl.cod_cli = 1 AND es.cod_est = 1 AND pv.empr_id = 1 AND pv.tipo = 'PDV' AND ip.qtde_sldo > 0
                   AND pv.dt_entrega BETWEEN :ini2 AND :fim2
                   AND ic.itempr_id = b.item_empr_id
                   AND (b.masc_id IS NULL OR ip.tmasc_item_id = b.masc_id))
           ELSE NVL(pf.itf, 0) END pendente,
           NVL(pf.dd, 0) demanda,
           CASE WHEN b.masc_id IS NULL THEN
               (SELECT MAX(f.nome) FROM focco3i.titens_plan_func itf, focco3i.tfuncionarios f
                 WHERE itf.itpl_id = b.planejamento_id AND itf.func_id = f.id AND itf.ranking = 99)
           ELSE
               (SELECT MAX(f.nome) FROM focco3i.titens_plan_conf pc1, focco3i.titens_plan_func_conf fc, focco3i.tfuncionarios f
                 WHERE pc1.id = fc.it_pl_conf_id AND f.id = fc.func_id AND fc.ranking = 99 AND pc1.tmasc_item_id = b.masc_id)
           END planejador,
           CASE WHEN b.empr_id = 1 THEN CASE WHEN b.masc_id IS NULL THEN vs.pdv_id ELSE vc.pdv_id END END pdv_id,
           CASE WHEN b.empr_id <> 1 THEN CASE WHEN b.masc_id IS NULL THEN cs.pedc_id ELSE cc.pedc_id END END pedc_id,
           CASE WHEN b.empr_id <> 1 THEN CASE WHEN b.masc_id IS NULL THEN cs.dt_ent ELSE cc.dt_ent END END pedc_dt_ent
      FROM base b
      JOIN emp ep ON ep.empr_id = b.empr_id
      LEFT JOIN mov_sm ms ON ms.item_empr_id = b.item_empr_id
      LEFT JOIN mov    mc ON mc.item_empr_id = b.item_empr_id AND mc.masc_id = b.masc_id
      LEFT JOIN perf   pf ON pf.itpl_id = b.planejamento_id AND pf.mk = NVL(b.masc_id, 1)
      LEFT JOIN pdv_sm vs ON vs.itempr_id = b.item_empr_id
      LEFT JOIN pdv_cm vc ON vc.itempr_id = b.item_empr_id AND vc.tmasc_item_id = b.masc_id
      LEFT JOIN pdc_sm cs ON cs.empr_id = b.empr_id AND cs.item_id = b.item_id
      LEFT JOIN pdc_cm cc ON cc.empr_id = b.empr_id AND cc.item_id = b.item_id AND cc.masc_item_id = b.masc_id
)
SELECT c.item_id, c.cod_item, c.desc_item, c.item_empr_id, c.empr_id, c.cod_grp_ite, c.masc_id, c.mascara,
       c.lote_mult, c.estq_seg, c.media_trimestral, c.media_mensal, c.saldo_cd, c.dias_est, 0 saldo_filial,
       NVL(pv.num_pedido, pc.cod_pedc)                                         pedido,
       CASE WHEN c.empr_id = 1 THEN pv.dt_emis ELSE pc.dt_emis END             inclusao,
       CASE WHEN c.empr_id = 1 THEN TRUNC(pv.dt_entrega) ELSE TRUNC(c.pedc_dt_ent) END entrega,
       CASE WHEN c.empr_id = 1 AND pv.id IS NOT NULL THEN
           (SELECT SUM(ip.qtde_sldo * focco3i.adm_inv_busca_taxa_conversao(pv.empr_id, e1.item_id,
                        CASE WHEN c.masc_id IS NULL THEN NULL ELSE ip.tmasc_item_id END, ip.unid_med_id, s1.unid_med_id))
              FROM focco3i.titens_pdv ip, focco3i.titens_comercial ic, focco3i.titens_estoque s1, focco3i.titens_empr e1
             WHERE ip.pdv_id = pv.id AND ic.id = ip.itcm_id AND ic.itempr_id = e1.id AND s1.itempr_id = e1.id
               AND e1.id = c.item_empr_id AND (c.masc_id IS NULL OR ip.tmasc_item_id = c.masc_id))
            WHEN c.empr_id <> 1 AND pc.id IS NOT NULL THEN
           (SELECT SUM(i1.qtde_saldo * focco3i.adm_inv_busca_taxa_conversao(pc.empr_id, e1.item_id,
                        CASE WHEN c.masc_id IS NULL THEN NULL ELSE i1.masc_item_id END, i1.unid_med_id, s1.unid_med_id))
              FROM focco3i.tpedc_item i1, focco3i.titens_suprimentos ts1, focco3i.titens_estoque s1, focco3i.titens_empr e1
             WHERE i1.tpedc_id = pc.id AND ts1.id = i1.item_id AND e1.id = ts1.itempr_id AND e1.id = s1.itempr_id
               AND i1.qtde_saldo > 0 AND e1.item_id = c.item_id AND (c.masc_id IS NULL OR i1.masc_item_id = c.masc_id))
       END total,
       c.pendente, c.demanda, c.planejador, c.lote_min, c.tempo_rep, c.tipo_produto,
       c.almox_padrao, c.outros_almox, c.peso,
       ABS(((c.almox_padrao + TRUNC(NVL(c.pendente, 0))) - TRUNC(c.estq_seg)) - ROUND(NVL(c.demanda, 0))) qtde_alt,
       c.planejamento_id, c.cod_grp_invent
  FROM calc c
  LEFT JOIN focco3i.tpedidos_venda pv ON pv.id = c.pdv_id
  LEFT JOIN focco3i.tped_compra    pc ON pc.id = c.pedc_id
~';

    PROCEDURE GERAR (pi_usuario IN VARCHAR2, pi_empresas IN VARCHAR2, pi_dt_ini IN DATE, pi_dt_fim IN DATE, po_sessao OUT NUMBER) IS
        v_ini     DATE := TRUNC(pi_dt_ini);
        v_fim     DATE := TRUNC(pi_dt_fim);
        v_inicio  DATE := SYSDATE;
        v_qtd     NUMBER;
        v_sql     VARCHAR2(32767);
    BEGIN
        -- segurança: a lista de empresas entra no texto do SQL, então só aceita números e vírgulas
        IF pi_empresas IS NULL OR NOT REGEXP_LIKE(pi_empresas, '^[0-9]+(,[0-9]+)*$') THEN
            RAISE_APPLICATION_ERROR(-20010, 'Lista de empresas inválida.');
        END IF;
        IF v_ini IS NULL OR v_fim IS NULL OR v_fim < v_ini THEN
            RAISE_APPLICATION_ERROR(-20011, 'Período inválido.');
        END IF;

        -- descarta a geração anterior do usuário
        FOR r IN (SELECT SESSAO FROM TGAZIN_PMF_PAR WHERE USUARIO = pi_usuario) LOOP
            DELETE FROM TGAZIN_PMF     WHERE SESSAO = r.SESSAO;
            DELETE FROM TGAZIN_PMF_MSG WHERE SESSAO = r.SESSAO;
            DELETE FROM TGAZIN_PMF_PAR WHERE SESSAO = r.SESSAO;
        END LOOP;

        SELECT SEQ_TGAZIN_PMF_SESSAO.NEXTVAL INTO po_sessao FROM DUAL;

        v_sql := 'INSERT INTO TGAZIN_PMF (ID, SESSAO, COD_ITEM, DESC_ITEM, ITEM_ID, ITEM_EMPR_ID, EMPR_ID, COD_GRP_ITE,
                        MASC_ID, MASCARA, LOTE_MULT, ESTQ_SEG, MEDIA_TRIMESTRAL, MEDIA_MENSAL, SALDO_CD, DIAS_EST,
                        SALDO_FILIAL, PEDIDO, INCLUSAO, ENTREGA, TOTAL, PENDENTE, DEMANDA, PLANEJADOR, LOTE_MIN,
                        TEMPO_REP, TIPO_PRODUTO, ALMOX_PADRAO, OUTROS_ALMOX, NECESSIDADE, SELECAO, PESO, QTDE_ALT,
                        PLANEJAMENTO_ID, DT_SESSAO, ESTOQUE, COD_GRP_INVENT)
                  SELECT SEQ_TGAZIN_PMF.NEXTVAL, ' || po_sessao || ', x.cod_item, x.desc_item, x.item_id, x.item_empr_id,
                         x.empr_id, x.cod_grp_ite, x.masc_id, x.mascara, x.lote_mult, x.estq_seg, x.media_trimestral,
                         x.media_mensal, x.saldo_cd, x.dias_est, x.saldo_filial, x.pedido, x.inclusao, x.entrega, x.total,
                         x.pendente, x.demanda, x.planejador, x.lote_min, x.tempo_rep, x.tipo_produto, x.almox_padrao,
                         x.outros_almox, 0, 0, x.peso, x.qtde_alt, x.planejamento_id, TRUNC(SYSDATE), 0, x.cod_grp_invent
                    FROM (' || REPLACE(c_sql, '{EMPRESAS}', pi_empresas) || ') x';

        EXECUTE IMMEDIATE v_sql USING v_ini, v_fim, v_ini, v_fim;
        v_qtd := SQL%ROWCOUNT;

        INSERT INTO TGAZIN_PMF_PAR (SESSAO, USUARIO, EMPRESAS, DT_INI, DT_FIM, DT_GERACAO, SEGUNDOS, LINHAS)
             VALUES (po_sessao, pi_usuario, pi_empresas, v_ini, v_fim, SYSDATE, ROUND((SYSDATE - v_inicio) * 86400), v_qtd);

        COMMIT;
    EXCEPTION WHEN OTHERS THEN
        ROLLBACK;
        RAISE;
    END GERAR;

END GAZIN_PMF;
/
