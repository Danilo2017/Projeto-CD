-- =====================================================================
-- Pacote GAZIN_PLA_REP_CD — geração otimizada e alterações da tela
-- Executar como FOCCO3I. Separador de comando: linha com "/".
--
-- Ganho em relação a OTIMIZA.PPLA_REP_CD_OTM (~460 s -> ~25 s):
--  * TPERFIL_ITENS (demanda DD / pendente OCL) agregado UMA vez para todos os itens,
--    em vez de chamar F3IMOV_DADOS_PERFIL_ITEM_APOS ~17x por linha (a função ainda
--    calculava RETORNA_SALDO a cada chamada e descartava o resultado);
--  * primeiro pedido de compra pendente calculado uma vez (KEEP DENSE_RANK FIRST),
--    em vez de 4 subconsultas aninhadas por linha;
--  * saídas de estoque de 90 e 30 dias lidas em uma única passada;
--  * plano 21 (TGAZIN_PLANOS_PLAN) em uma junção, em vez de 4 subconsultas.
-- Mesma regra de negócio: validado contra a TTEMP_PLA_REP_CD_OTM (1010 linhas iguais).
-- =====================================================================
CREATE OR REPLACE PACKAGE GAZIN_PLA_REP_CD AS
    PROCEDURE GERAR (pi_usuario IN VARCHAR2 DEFAULT 'JOB');

    PROCEDURE ALTERAR_ESTOQUE_SEG (pi_id IN NUMBER, pi_estq_seg IN NUMBER);

    PROCEDURE ALTERAR_STATUS (pi_id      IN NUMBER,
                              pi_status  IN VARCHAR2,   -- 'A' ativo / 'N' inativo
                              pi_obs     IN VARCHAR2,
                              pi_usuario IN VARCHAR2);

    PROCEDURE ALTERAR_PLANEJADOR (pi_id         IN  NUMBER,
                                  pi_func_id    IN  NUMBER,
                                  po_planejador OUT VARCHAR2);
END GAZIN_PLA_REP_CD;
/

CREATE OR REPLACE PACKAGE BODY GAZIN_PLA_REP_CD AS

    e_ocupado EXCEPTION;
    PRAGMA EXCEPTION_INIT(e_ocupado, -54);

    PROCEDURE registrar_erro (pi_usuario VARCHAR2, pi_ini DATE, pi_erro VARCHAR2) IS
        PRAGMA AUTONOMOUS_TRANSACTION;
    BEGIN
        UPDATE TGAZIN_PLA_REP_CD_CTRL
           SET STATUS = 'ERRO', DT_INICIO = pi_ini, DT_FIM = SYSDATE,
               USUARIO = pi_usuario, ERRO = SUBSTR(pi_erro, 1, 4000)
         WHERE ID = 1;
        COMMIT;
    END;

    PROCEDURE carregar_linha (pi_id IN NUMBER, po_lin OUT TGAZIN_PLA_REP_CD%ROWTYPE) IS
    BEGIN
        SELECT * INTO po_lin FROM TGAZIN_PLA_REP_CD WHERE ID = pi_id;
    EXCEPTION WHEN NO_DATA_FOUND THEN
        RAISE_APPLICATION_ERROR(-20002, 'O planejamento foi recalculado. Atualize a tela e tente novamente.');
    END;

    -- ─────────────────────────────────────────────────────────────────
    PROCEDURE GERAR (pi_usuario IN VARCHAR2 DEFAULT 'JOB') IS
        v_ini   DATE := SYSDATE;
        v_qtd   NUMBER;
        v_trava NUMBER;
    BEGIN
        BEGIN
            SELECT ID INTO v_trava FROM TGAZIN_PLA_REP_CD_CTRL WHERE ID = 1 FOR UPDATE NOWAIT;
        EXCEPTION WHEN e_ocupado THEN
            RAISE_APPLICATION_ERROR(-20001, 'Já existe um recálculo em andamento. Aguarde alguns segundos.');
        END;

        -- Leitores continuam vendo o snapshot anterior até o COMMIT
        DELETE FROM TGAZIN_PLA_REP_CD;

        INSERT INTO TGAZIN_PLA_REP_CD (
               ID, ITEM_ID, COD_ITEM, DESC_ITEM, ITEM_EMPR_ID, EMPR_ID, COD_GRP_ITE, MASC_ID, MASCARA,
               LOTE_MULT, ESTQ_SEG, MEDIA_TRIMESTRAL, MEDIA_MENSAL, SALDO_CD, DIAS_EST, SALDO_FILIAL,
               PEDIDO, INCLUSAO, ENTREGA, TOTAL, PENDENTE, DEMANDA, PLANEJADOR, LOTE_MIN, TEMPO_REP,
               TIPO_PRODUTO, PLANEJAMENTO_ID, DT_GERACAO)
        SELECT SEQ_TGAZIN_PLA_REP_CD.NEXTVAL,
               x.item_id, x.cod_item, x.desc_item, x.item_empr_id, x.empr_id, x.cod_grp_ite, x.masc_id, x.mascara,
               x.lote_mult, x.estq_seg, x.media_trimestral, x.media_mensal, x.saldo_cd, x.dias_est, x.saldo_filial,
               x.pedido, x.inclusao, x.entrega, x.total, x.pendente, x.demanda, x.planejador, x.lote_min, x.tempo_rep,
               x.tipo_produto, x.planejamento_id, v_ini
          FROM (
            WITH
            base AS (
                -- itens sem máscara (não configurados)
                SELECT t.id item_id, t.cod_item, t.desc_tecnica desc_item, te.id item_empr_id, te.empr_id, g.cod_grp_ite,
                       CAST(NULL AS NUMBER) masc_id, CAST(NULL AS VARCHAR2(4000)) mascara,
                       tp.id planejamento_id, eng.tipo_produto, NVL(tp.cons_medio,0) / 22 dias_est
                  FROM titens t, titens_empr te, titens_engenharia eng, tgrp_clas_ite g,
                       titens_planejamento tp, titens_estoque es, talmoxarifados al
                 WHERE t.id = te.item_id AND te.id = eng.itempr_id AND te.id = tp.itempr_id AND g.id = eng.grp_clas_id
                   AND te.id = es.itempr_id AND al.id = es.almox_id AND al.cod_almox = '1'
                   AND eng.sit = 1 AND t.sit = 1 AND te.sit = 1 AND t.configurado = 0
                   AND te.empr_id = 1 AND g.cod_grp_ite LIKE '10.%'
                UNION ALL
                -- itens configurados (uma linha por máscara)
                SELECT t.id, t.cod_item, t.desc_tecnica, te.id, te.empr_id, g.cod_grp_ite,
                       m.id, m.mascara, tp.id, ec.tipo_produto, NVL(pc.cons_medio,0) / 22
                  FROM titens t, titens_empr te, titens_engenharia eng, tgrp_clas_ite g,
                       titens_eng_conf ec, tmasc_item m, titens_planejamento tp,
                       titens_plan_conf pc, titens_estoque es, titens_estq_conf eqc, talmoxarifados al
                 WHERE t.id = te.item_id AND te.id = eng.itempr_id AND eng.id = ec.iteg_id AND g.id = eng.grp_clas_id
                   AND m.id = ec.tmasc_item_id AND te.id = tp.itempr_id AND tp.id = pc.itpl_id AND m.id = pc.tmasc_item_id
                   AND te.id = es.itempr_id AND es.id = eqc.itestq_id AND m.id = eqc.tmasc_item_id AND al.id = eqc.almox_id
                   AND al.cod_almox = '1' AND eng.sit = 1 AND t.sit = 1 AND te.sit = 1 AND t.configurado = 1 AND m.sit = 1
                   AND te.empr_id = 1 AND g.cod_grp_ite LIKE '10.%'
            ),
            itens AS (SELECT DISTINCT item_id FROM base),
            -- saídas de estoque das filiais do grupo 1003: 90 dias e 30 dias em uma única leitura
            mov AS (
                SELECT emp.item_id, masc.mascara,
                       SUM(mov.qtde) q90,
                       SUM(CASE WHEN mov.dt >= TRUNC(SYSDATE) - 30 THEN mov.qtde END) q30
                  FROM tmov_estq mov, titens_estoque itest, titens_empr emp,
                       tgrp_clas_ite grp, ttp_mov_estq tmov, tempresas e, tmasc_item masc
                 WHERE mov.itestq_id = itest.id AND itest.grp_clas_id = grp.id AND mov.tmves_id = tmov.id
                   AND mov.ent_sai = 'S' AND mov.dt BETWEEN TRUNC(SYSDATE) - 90 AND TRUNC(SYSDATE)
                   AND grp.cod_grp_ite LIKE '10%' AND tmov.sigla IN ('REP','REQ','NFS','RSI')
                   AND itest.itempr_id = emp.id AND emp.empr_id = e.id AND e.empr_id = 1003
                   AND masc.id(+) = mov.tmasc_item_id
                   AND emp.item_id IN (SELECT item_id FROM itens)
                 GROUP BY emp.item_id, masc.mascara
            ),
            mov_item AS (SELECT item_id, SUM(q90) q90, SUM(q30) q30 FROM mov GROUP BY item_id),
            -- linhas de pedido de compra pendentes na filial 1
            ped AS (
                SELECT te.item_id, pi.masc_item_id, pc.id pedc_id, pi.dt_entrega
                  FROM tped_compra pc, tpedc_item pi, titens_suprimentos ts, titens_empr te
                 WHERE pc.id = pi.tpedc_id AND ts.id = pi.item_id AND te.id = ts.itempr_id
                   AND pi.qtde_saldo > 0 AND pc.posicao = 'P' AND pc.empr_id = 1
                   AND te.item_id IN (SELECT item_id FROM itens)
            ),
            -- primeiro pedido = menor data de entrega; empate -> menor ID do pedido
            ped_sm AS (SELECT item_id, MIN(dt_entrega) dt_ent,
                              MIN(pedc_id) KEEP (DENSE_RANK FIRST ORDER BY dt_entrega) pedc_id
                         FROM ped GROUP BY item_id),
            ped_cm AS (SELECT item_id, masc_item_id, MIN(dt_entrega) dt_ent,
                              MIN(pedc_id) KEEP (DENSE_RANK FIRST ORDER BY dt_entrega) pedc_id
                         FROM ped WHERE masc_item_id IS NOT NULL GROUP BY item_id, masc_item_id),
            -- perfil do MRP: DD (plano cód. 1) e OCL (plano cód. 3) do ano em diante, agregado uma vez
            -- (mesma regra da F3IMOV_DADOS_PERFIL_ITEM_APOS: DT_FIM entre 1º/jan+1 e 1º/jan+999)
            perf AS (
                SELECT p.itpl_id, NVL(p.tmasc_item_id, 1) mk,
                       SUM(CASE WHEN pl.cod_plano = 1 AND p.tipo_ord_dem = 'DD'  THEN p.qtde END) dd,
                       SUM(CASE WHEN pl.cod_plano = 3 AND p.tipo_ord_dem = 'OCL' THEN p.qtde END) ocl
                  FROM tperfil_itens p, tplanos pl
                 WHERE pl.id = p.plano_id AND pl.cod_plano IN (1,3) AND p.tipo_ord_dem IN ('DD','OCL')
                   AND p.dt_fim >= TRUNC(SYSDATE,'YEAR') + 1 AND p.dt_fim < TRUNC(SYSDATE,'YEAR') + 1000
                   AND p.itpl_id IN (SELECT tp.id FROM titens_planejamento tp, titens_empr te
                                      WHERE tp.itempr_id = te.id AND te.item_id IN (SELECT item_id FROM itens))
                 GROUP BY p.itpl_id, NVL(p.tmasc_item_id, 1)
            ),
            dem_sm AS (
                SELECT te1.item_id, ROUND(SUM(NVL(pf.dd,0))) demanda
                  FROM titens_planejamento tp1, titens_empr te1, perf pf
                 WHERE te1.id = tp1.itempr_id AND te1.empr_id NOT IN (10,11)
                   AND te1.item_id IN (SELECT item_id FROM itens)
                   AND pf.itpl_id(+) = tp1.id AND pf.mk(+) = 1
                 GROUP BY te1.item_id
            ),
            dem_cm AS (
                SELECT te1.item_id, m1.mascara, ROUND(SUM(NVL(pf.dd,0))) demanda
                  FROM titens_planejamento tp1, titens_plan_conf pc1, tmasc_item m1, titens_empr te1, perf pf
                 WHERE te1.id = tp1.itempr_id AND tp1.id = pc1.itpl_id AND pc1.tmasc_item_id = m1.id
                   AND te1.empr_id NOT IN (10,11) AND te1.item_id IN (SELECT item_id FROM itens)
                   AND pf.itpl_id(+) = tp1.id AND pf.mk(+) = m1.id
                 GROUP BY te1.item_id, m1.mascara
            ),
            plano AS (SELECT itempr_id, tmasc_item_id, lote_mult, estq_seg, lote_min, tempo_rep
                        FROM tgazin_planos_plan WHERE plano_id = 21)
            SELECT b.item_id, b.cod_item, b.desc_item, b.item_empr_id, b.empr_id, b.cod_grp_ite, b.masc_id, b.mascara,
                   pl.lote_mult, pl.estq_seg,
                   CASE WHEN b.masc_id IS NULL THEN mi.q90 ELSE mv.q90 END / 3          media_trimestral,
                   CASE WHEN b.masc_id IS NULL THEN mi.q30 ELSE mv.q30 END              media_mensal,
                   man_est_retorna_saldo(1, b.item_id, '1', SYSDATE, NULL, b.masc_id)   saldo_cd,
                   b.dias_est,
                   CASE WHEN b.masc_id IS NULL THEN
                       (SELECT SUM(man_est_retorna_saldo(te1.empr_id, te1.item_id, '4,5,6,73', SYSDATE, NULL, NULL))
                          FROM titens t1, titens_empr te1, titens_engenharia en1
                         WHERE t1.id = te1.item_id AND te1.id = en1.itempr_id AND en1.sit = 1 AND t1.configurado = 0
                           AND t1.id = b.item_id AND te1.empr_id NOT IN (10,11))
                   ELSE
                       (SELECT SUM(man_est_retorna_saldo(te1.empr_id, te1.item_id, '4,5,6,73', SYSDATE, NULL, m1.id))
                          FROM titens t1, titens_empr te1, titens_engenharia en1, titens_eng_conf ec1, tmasc_item m1
                         WHERE t1.id = te1.item_id AND te1.id = en1.itempr_id AND en1.id = ec1.iteg_id
                           AND m1.id = ec1.tmasc_item_id AND m1.sit = 1 AND en1.sit = 1 AND t1.configurado = 1
                           AND t1.id = b.item_id AND m1.mascara = b.mascara AND te1.empr_id NOT IN (10,11))
                   END saldo_filial,
                   pc.cod_pedc pedido,
                   pc.dt_emis  inclusao,
                   TRUNC(CASE WHEN b.masc_id IS NULL THEN ps.dt_ent ELSE pm.dt_ent END) entrega,
                   CASE WHEN pc.id IS NOT NULL THEN
                       (SELECT SUM(i1.qtde_saldo * adm_inv_busca_taxa_conversao(pc.empr_id, te1.item_id,
                                    CASE WHEN b.masc_id IS NULL THEN NULL ELSE i1.masc_item_id END, i1.unid_med_id, es1.unid_med_id))
                          FROM tpedc_item i1, titens_suprimentos ts1, titens_estoque es1, titens_empr te1
                         WHERE i1.tpedc_id = pc.id AND ts1.id = i1.item_id AND te1.id = ts1.itempr_id AND te1.id = es1.itempr_id
                           AND i1.qtde_saldo > 0 AND te1.item_id = b.item_id
                           AND (b.masc_id IS NULL OR i1.masc_item_id = b.masc_id))
                   END total,
                   NVL(pf.ocl, 0) pendente,
                   CASE WHEN b.masc_id IS NULL THEN ds.demanda ELSE dc.demanda END demanda,
                   CASE WHEN b.masc_id IS NULL THEN
                       (SELECT MAX(f.nome) KEEP (DENSE_RANK LAST ORDER BY itf.ranking)
                          FROM titens_plan_func itf, tfuncionarios f
                         WHERE itf.itpl_id = b.planejamento_id AND itf.func_id = f.id)
                   ELSE
                       (SELECT MAX(f.nome) KEEP (DENSE_RANK LAST ORDER BY fc.ranking)
                          FROM titens_plan_conf pc1, titens_plan_func_conf fc, tfuncionarios f
                         WHERE pc1.id = fc.it_pl_conf_id AND f.id = fc.func_id AND pc1.tmasc_item_id = b.masc_id)
                   END planejador,
                   pl.lote_min, pl.tempo_rep, b.tipo_produto, b.planejamento_id
              FROM base b
              LEFT JOIN plano    pl ON pl.itempr_id = b.item_empr_id AND NVL(pl.tmasc_item_id, 0) = NVL(b.masc_id, 0)
              LEFT JOIN mov      mv ON mv.item_id = b.item_id AND mv.mascara = b.mascara
              LEFT JOIN mov_item mi ON mi.item_id = b.item_id
              LEFT JOIN ped_sm   ps ON ps.item_id = b.item_id
              LEFT JOIN ped_cm   pm ON pm.item_id = b.item_id AND pm.masc_item_id = b.masc_id
              LEFT JOIN tped_compra pc ON pc.id = CASE WHEN b.masc_id IS NULL THEN ps.pedc_id ELSE pm.pedc_id END
              LEFT JOIN perf     pf ON pf.itpl_id = b.planejamento_id AND pf.mk = NVL(b.masc_id, 1)
              LEFT JOIN dem_sm   ds ON ds.item_id = b.item_id
              LEFT JOIN dem_cm   dc ON dc.item_id = b.item_id AND dc.mascara = b.mascara
          ) x;

        v_qtd := SQL%ROWCOUNT;

        UPDATE TGAZIN_PLA_REP_CD_CTRL
           SET STATUS = 'OK', DT_INICIO = v_ini, DT_FIM = SYSDATE,
               SEGUNDOS = ROUND((SYSDATE - v_ini) * 86400), LINHAS = v_qtd,
               USUARIO = pi_usuario, ERRO = NULL
         WHERE ID = 1;

        COMMIT;
    EXCEPTION WHEN OTHERS THEN
        ROLLBACK;
        IF SQLCODE <> -20001 THEN
            registrar_erro(pi_usuario, v_ini, SQLERRM);
        END IF;
        RAISE;
    END GERAR;

    -- ─────────────────────────────────────────────────────────────────
    PROCEDURE ALTERAR_ESTOQUE_SEG (pi_id IN NUMBER, pi_estq_seg IN NUMBER) IS
        v_lin TGAZIN_PLA_REP_CD%ROWTYPE;
        v_seg NUMBER := NVL(pi_estq_seg, 0);
    BEGIN
        carregar_linha(pi_id, v_lin);

        UPDATE TGAZIN_PLA_REP_CD SET ESTQ_SEG = v_seg WHERE ID = pi_id;

        UPDATE TGAZIN_PLANOS_PLAN SET ESTQ_SEG = v_seg
         WHERE ITEMPR_ID = v_lin.ITEM_EMPR_ID
           AND NVL(TMASC_ITEM_ID, 0) = NVL(v_lin.MASC_ID, 0)
           AND PLANO_ID = 21;

        COMMIT;
    END ALTERAR_ESTOQUE_SEG;

    -- ─────────────────────────────────────────────────────────────────
    PROCEDURE ALTERAR_STATUS (pi_id IN NUMBER, pi_status IN VARCHAR2, pi_obs IN VARCHAR2, pi_usuario IN VARCHAR2) IS
        v_lin    TGAZIN_PLA_REP_CD%ROWTYPE;
        v_status VARCHAR2(1) := CASE WHEN pi_status = 'N' THEN 'N' ELSE NULL END;
    BEGIN
        carregar_linha(pi_id, v_lin);

        UPDATE TGAZIN_PLA_REP_CD SET TIPO_PRODUTO = v_status WHERE ID = pi_id;

        INSERT INTO TGAZIN_PLA_REP_CD_OBS (ID, ITEM_ID, ITEM_EMPR_ID, MASC_ID, STATUS, DATA, OBS, USUARIO)
             VALUES (SEQ_TGAZIN_PLA_REP_CD_OBS.NEXTVAL, v_lin.ITEM_ID, v_lin.ITEM_EMPR_ID, v_lin.MASC_ID,
                     v_status, SYSDATE, SUBSTR(UPPER(pi_obs), 1, 100), SUBSTR(pi_usuario, 1, 60));

        -- Mesma regra do Focco: altera o tipo de produto em todas as empresas do grupo 1003
        IF v_lin.MASC_ID IS NULL THEN
            UPDATE TITENS_ENGENHARIA en SET en.TIPO_PRODUTO = v_status
             WHERE en.ID IN (SELECT en2.ID
                               FROM TITENS_EMPR te, TITENS_ENGENHARIA en2
                              WHERE te.ID = en2.ITEMPR_ID
                                AND te.ITEM_ID = v_lin.ITEM_ID
                                AND EXISTS (SELECT 1 FROM TEMPRESAS e WHERE e.ID = te.EMPR_ID AND e.EMPR_ID = 1003));
        ELSE
            UPDATE TITENS_ENG_CONF ec SET ec.TIPO_PRODUTO = v_status
             WHERE ec.ID IN (SELECT ec2.ID
                               FROM TITENS_EMPR te, TITENS_ENGENHARIA en, TITENS_ENG_CONF ec2, TMASC_ITEM m
                              WHERE te.ID = en.ITEMPR_ID AND en.ID = ec2.ITEG_ID AND m.ID = ec2.TMASC_ITEM_ID
                                AND te.ITEM_ID = v_lin.ITEM_ID
                                AND m.MASCARA = v_lin.MASCARA
                                AND EXISTS (SELECT 1 FROM TEMPRESAS e WHERE e.ID = te.EMPR_ID AND e.EMPR_ID = 1003));
        END IF;

        COMMIT;
    END ALTERAR_STATUS;

    -- ─────────────────────────────────────────────────────────────────
    -- Mesma lógica do "Alterar Planejador" do Focco: grava o planejador (ranking 99) em todas
    -- as filiais do item; cria o funcionário na filial se ainda não existir lá.
    PROCEDURE ALTERAR_PLANEJADOR (pi_id IN NUMBER, pi_func_id IN NUMBER, po_planejador OUT VARCHAR2) IS
        v_lin          TGAZIN_PLA_REP_CD%ROWTYPE;
        v_cod_func     TFUNCIONARIOS.COD_FUNC%TYPE;
        v_func         NUMBER;
        v_seq_func     NUMBER;
        v_seq_pla_func NUMBER;
        v_id_pla_func  NUMBER;
    BEGIN
        carregar_linha(pi_id, v_lin);

        SELECT COD_FUNC, NOME INTO v_cod_func, po_planejador FROM TFUNCIONARIOS WHERE ID = pi_func_id;

        UPDATE TGAZIN_PLA_REP_CD SET PLANEJADOR = po_planejador WHERE ID = pi_id;

        IF v_lin.MASCARA IS NULL THEN
            FOR c IN (SELECT DISTINCT emp.ID emp_id, emp.EMPR_ID empresa, itf.ITPL_ID pla_id
                        FROM TITENS_PLAN_FUNC itf, TITENS_PLANEJAMENTO pla, TITENS_EMPR emp
                       WHERE itf.ITPL_ID = pla.ID AND pla.ITEMPR_ID = emp.ID AND emp.ITEM_ID = v_lin.ITEM_ID)
            LOOP
                BEGIN
                    SELECT ID INTO v_func FROM TFUNCIONARIOS WHERE NOME = po_planejador AND EMPR_ID = c.empresa;
                EXCEPTION WHEN OTHERS THEN v_func := NULL;
                END;

                IF v_func IS NULL THEN
                    SELECT SEQ_ID_TFUNCIONARIOS.NEXTVAL, SEQ_ID_TITENS_PLAN_FUNC.NEXTVAL
                      INTO v_seq_func, v_seq_pla_func FROM DUAL;

                    INSERT INTO TFUNCIONARIOS VALUES (v_seq_func, v_cod_func, po_planejador, 1, c.empresa, NULL, 0, 0);

                    SELECT COUNT(*) INTO v_id_pla_func FROM TITENS_PLAN_FUNC WHERE ITPL_ID = c.pla_id AND RANKING = 99;
                    IF v_id_pla_func = 0 THEN
                        INSERT INTO TITENS_PLAN_FUNC VALUES (v_seq_pla_func, v_seq_func, c.pla_id, 99, NULL);
                    END IF;
                ELSE
                    BEGIN
                        SELECT itf.ID INTO v_id_pla_func
                          FROM TITENS_PLAN_FUNC itf, TITENS_PLANEJAMENTO pla, TITENS_EMPR emp
                         WHERE itf.ITPL_ID = pla.ID AND pla.ITEMPR_ID = emp.ID AND itf.RANKING = 99 AND emp.ID = c.emp_id;
                    EXCEPTION WHEN OTHERS THEN v_id_pla_func := NULL;
                    END;

                    IF v_id_pla_func IS NULL THEN
                        SELECT SEQ_ID_TITENS_PLAN_FUNC.NEXTVAL INTO v_seq_pla_func FROM DUAL;
                        INSERT INTO TITENS_PLAN_FUNC VALUES (v_seq_pla_func, v_func, c.pla_id, 99, NULL);
                    ELSE
                        UPDATE TITENS_PLAN_FUNC SET FUNC_ID = v_func WHERE ID = v_id_pla_func;
                    END IF;
                END IF;
            END LOOP;
        ELSE
            FOR c IN (SELECT DISTINCT m1.ID masc_id, te1.EMPR_ID empresa, pc1.ID pla_id
                        FROM TITENS_PLANEJAMENTO tp1, TITENS_PLAN_CONF pc1, TMASC_ITEM m1, TITENS_EMPR te1
                       WHERE tp1.ID = pc1.ITPL_ID AND pc1.TMASC_ITEM_ID = m1.ID AND te1.ID = tp1.ITEMPR_ID
                         AND te1.ITEM_ID = v_lin.ITEM_ID AND m1.MASCARA = v_lin.MASCARA)
            LOOP
                BEGIN
                    SELECT ID INTO v_func FROM TFUNCIONARIOS WHERE NOME = po_planejador AND EMPR_ID = c.empresa;
                EXCEPTION WHEN OTHERS THEN v_func := NULL;
                END;

                IF v_func IS NULL THEN
                    SELECT SEQ_ID_TFUNCIONARIOS.NEXTVAL, SEQ_ID_TITENS_PLAN_FUNC_CONF.NEXTVAL
                      INTO v_seq_func, v_seq_pla_func FROM DUAL;

                    INSERT INTO TFUNCIONARIOS VALUES (v_seq_func, v_cod_func, po_planejador, 1, c.empresa, NULL, 0, 0);

                    SELECT COUNT(*) INTO v_id_pla_func FROM TITENS_PLAN_FUNC_CONF WHERE IT_PL_CONF_ID = c.pla_id AND RANKING = 99;
                    IF v_id_pla_func = 0 THEN
                        INSERT INTO TITENS_PLAN_FUNC_CONF VALUES (v_seq_pla_func, 99, c.pla_id, v_seq_func);
                    END IF;
                ELSE
                    SELECT MAX(fc.ID) INTO v_id_pla_func
                      FROM TITENS_PLAN_CONF pc, TITENS_PLAN_FUNC_CONF fc
                     WHERE pc.ID = fc.IT_PL_CONF_ID AND pc.ID = c.pla_id AND fc.RANKING = 99 AND pc.TMASC_ITEM_ID = c.masc_id;

                    IF v_id_pla_func IS NULL THEN
                        SELECT SEQ_ID_TITENS_PLAN_FUNC_CONF.NEXTVAL INTO v_seq_pla_func FROM DUAL;
                        INSERT INTO TITENS_PLAN_FUNC_CONF VALUES (v_seq_pla_func, 99, c.pla_id, v_func);
                    ELSE
                        UPDATE TITENS_PLAN_FUNC_CONF SET FUNC_ID = v_func WHERE ID = v_id_pla_func;
                    END IF;
                END IF;
            END LOOP;
        END IF;

        COMMIT;
    END ALTERAR_PLANEJADOR;

END GAZIN_PLA_REP_CD;
/
