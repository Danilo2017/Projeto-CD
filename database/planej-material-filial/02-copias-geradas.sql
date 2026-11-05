-- =====================================================================
-- CÓPIAS GERADAS AUTOMATICAMENTE a partir do código-fonte original (all_source)
-- em 04/11/2026 08:31. Única alteração: nome da rotina e das tabelas de trabalho.
-- Os originais (OTIMIZA / FOCCO3I) NÃO são alterados. Executar como FOCCO3I.
-- =====================================================================

-- Cópia de OTIMIZA.FPARAM_ALMOX_OTM (FUNCTION)
CREATE OR REPLACE FUNCTION GAZIN_PMF_ALMOX_LISTA (
   pi_empr       IN   NUMBER,
   pi_tipo       IN   NUMBER
)
   RETURN VARCHAR2
IS
   V_ALMOX_PAD_I   varchar2(100);
   V_ALMOX_PAD_D   VARCHAR2(100);
   V_OUTRS_ALMOX_I VARCHAR2(100);
   V_OUTRS_ALMOX_D VARCHAR2(100);   
   V_QUEBRA        VARCHAR2(900);
   
BEGIN
   BEGIN
      SELECT ALMOX_PAD_IGUAL,
             '|'||replace(ALMOX_PAD_DIF,',','|,|')|| '|',
             OUTRS_ALMOX_IGUAL,
             '|'||replace(OUTRS_ALMOX_DIF,',','|,|')|| '|'
        INTO V_ALMOX_PAD_I,
             V_ALMOX_PAD_D,
             V_OUTRS_ALMOX_I,
             V_OUTRS_ALMOX_D
        FROM FOCCO3I.TGAZIN_PMF_PARS_ALMOX 
       where empr_id = pi_empr;
    EXCEPTION WHEN NO_DATA_FOUND THEN
             V_ALMOX_PAD_I := null;
             V_ALMOX_PAD_D := null;
             V_OUTRS_ALMOX_I := null;
             V_OUTRS_ALMOX_D := null;
    END;

    
    IF pi_tipo = 1 THEN  --SE FOR ALMOXARIFADO PADRÃO

        IF V_ALMOX_PAD_I IS NOT NULL THEN
            
            RETURN(V_ALMOX_PAD_I);
        
        ELSE
        
            BEGIN
                SELECT LISTAGG(COD_ALMOX, ',') WITHIN GROUP (ORDER BY COD_ALMOX)  
                  INTO V_QUEBRA
                  FROM FOCCO3I.TALMOXARIFADOS
                 WHERE EMPR_ID = pi_empr
                   AND instr(V_ALMOX_PAD_D, '|' || COD_ALMOX || '|' ) = 0;
                EXCEPTION WHEN NO_DATA_FOUND THEN
                 V_QUEBRA := null;
            END;
            
            RETURN(V_QUEBRA);
        
        END IF;
        
    
    ELSE --SE FOR OUTROS ALMOXARIFADO 
    
        IF V_OUTRS_ALMOX_I IS NOT NULL THEN
            
            RETURN(V_OUTRS_ALMOX_I);
        
        ELSE
        
             BEGIN
                SELECT  -- ''''||LISTAGG(COD_ALMOX, ''',''') WITHIN GROUP (ORDER BY COD_ALMOX)||'''' 
                     LISTAGG(COD_ALMOX, ',') WITHIN GROUP (ORDER BY COD_ALMOX)  
                  INTO V_QUEBRA
                  FROM FOCCO3I.TALMOXARIFADOS
                 WHERE EMPR_ID = pi_empr
                   --AND COD_ALMOX <> 'A0001'
                   AND instr(V_OUTRS_ALMOX_D, '|' || COD_ALMOX || '|' ) = 0;
                EXCEPTION WHEN NO_DATA_FOUND THEN
                 V_QUEBRA := null;
            END;
            
            RETURN(V_QUEBRA);
        
        END IF;
    
    END IF;  
    
END;
/

-- Cópia de OTIMIZA.PNECESS_PLA_MAT_OTM_GAZIN (FUNCTION)
CREATE OR REPLACE FUNCTION GAZIN_PMF_NECESSIDADE (
   pi_sessao   IN   NUMBER,
   pi_id       IN   NUMBER
)
   RETURN NUMBER
IS
   v_resposta   VARCHAR2 (100);
   v_valor      NUMBER;
BEGIN
   BEGIN
      SELECT CASE WHEN (((o.almox_padrao + TRUNC (NVL (o.pendente, 0), 0)) - TRUNC (o.estq_seg)) - ROUND (NVL (o.demanda, 0))) < 0 THEN 
                    CASE WHEN TRUNC (o.lote_mult) = 0 THEN 
                         (((o.almox_padrao + TRUNC (NVL (o.pendente, 0), 0)) - TRUNC (o.estq_seg)) - ROUND (NVL (o.demanda, 0))) --- 0 alterado pois não deve trazer 0
                    ELSE                         
                       CASE WHEN ABS(((o.almox_padrao + TRUNC (NVL (o.pendente, 0), 0)) - TRUNC (o.estq_seg)) - ROUND (NVL (o.demanda, 0))) <= o.lote_mult THEN
                                 TRUNC (o.lote_mult)  * (-1)           
                       ELSE                 
                            TRUNC (o.lote_mult) *                    
                                 CASE WHEN ((ABS(((o.almox_padrao + TRUNC(NVL (o.pendente,0),0)) - TRUNC (o.estq_seg)) - ROUND (NVL (o.demanda, 0)))
                                             / TRUNC (o.lote_mult)
                                            )
                                          - TRUNC((ABS(((o.almox_padrao + TRUNC(NVL(o.pendente,0),0))- TRUNC (o.estq_seg))- ROUND (NVL (o.demanda,0)))
                                               / TRUNC (o.lote_mult)))
                                         ) = 0 THEN 
                                             (ABS(((o.almox_padrao + TRUNC(NVL (o.pendente,0),0)) - TRUNC (o.estq_seg)) - ROUND (NVL (o.demanda, 0)))
                                                 / TRUNC (o.lote_mult)) * (-1)
                                 ELSE ROUND (FLOOR (((ABS(((o.almox_padrao + TRUNC(NVL(o.pendente,0),0)) - TRUNC(o.estq_seg)) - ROUND(NVL(o.demanda,0)))
                                                        / TRUNC (o.lote_mult)))* 2 + 1)
                                                    / 2
                                               ) * (-1)
                                 END

                       END
                   END    
             WHEN (((o.almox_padrao + TRUNC (NVL (o.pendente, 0), 0)) - TRUNC (o.estq_seg)) - ROUND (NVL (o.demanda, 0))) <= o.lote_mult
                   THEN o.lote_mult
             ELSE  
                     
                     CASE WHEN TRUNC (o.lote_mult) = 0 THEN 
                         (((o.almox_padrao + TRUNC (NVL (o.pendente, 0), 0)) - TRUNC (o.estq_seg)) - ROUND (NVL (o.demanda, 0))) --- 0 alterado pois não deve trazer 0
                     ELSE

                        TRUNC (o.lote_mult)
                        * CASE WHEN (((o.almox_padrao+ TRUNC (NVL (o.pendente, 0), 0))
                                      - TRUNC (o.estq_seg)
                                     ) - ROUND (NVL (o.demanda, 0))) > 0 THEN 
                                    CASE WHEN (  (  (  (  (o.almox_padrao + TRUNC(NVL (o.pendente,0),0))
                                                         - TRUNC (o.estq_seg))
                                                           - ROUND (NVL (o.demanda, 0))
                                                  )
                                                / TRUNC (o.lote_mult)
                                               )
                                             - TRUNC
                                                  ((  (  (  (  o.almox_padrao
                                                             + TRUNC
                                                                  (NVL
                                                                      (o.pendente,
                                                                       0
                                                                      ),
                                                                   0
                                                                  )
                                                            )
                                                          - TRUNC (o.estq_seg)
                                                         )
                                                       - ROUND (NVL (o.demanda,
                                                                     0)
                                                               )
                                                      )
                                                    / TRUNC (o.lote_mult)
                                                   )
                                                  )
                                            ) = 0
                                          THEN (  (  (  (  o.almox_padrao
                                                         + TRUNC
                                                               (NVL (o.pendente,
                                                                     0
                                                                    ),
                                                                0
                                                               )
                                                        )
                                                      - TRUNC (o.estq_seg)
                                                     )
                                                   - ROUND (NVL (o.demanda, 0))
                                                  )
                                                / TRUNC (o.lote_mult)
                                               )
                                       ELSE ROUND
                                              (  FLOOR
                                                    (    ((  (  (  (  o.almox_padrao
                                                                    + TRUNC
                                                                         (NVL
                                                                             (o.pendente,
                                                                              0
                                                                             ),
                                                                          0
                                                                         )
                                                                   )
                                                                 - TRUNC
                                                                      (o.estq_seg)
                                                                )
                                                              - ROUND
                                                                   (NVL
                                                                       (o.demanda,
                                                                        0
                                                                       )
                                                                   )
                                                             )
                                                           / TRUNC (o.lote_mult)
                                                          )
                                                         )
                                                       * 2
                                                     + 1
                                                    )
                                               / 2
                                              )
                                    END
                             ELSE CASE
                             WHEN (  (  (  (  (  o.almox_padrao
                                               + TRUNC (NVL (o.pendente, 0), 0)
                                              )
                                            - TRUNC (o.estq_seg)
                                           )
                                         - ROUND (NVL (o.demanda, 0))
                                        )
                                      / TRUNC (o.lote_mult)
                                     )
                                   - TRUNC ((  (  (  (  o.almox_padrao
                                                      + TRUNC (NVL (o.pendente,
                                                                    0),
                                                               0
                                                              )
                                                     )
                                                   - TRUNC (o.estq_seg)
                                                  )
                                                - ROUND (NVL (o.demanda, 0))
                                               )
                                             / TRUNC (o.lote_mult)
                                            )
                                           )
                                  ) = 0
                                THEN   (-1)
                                     * (  (  (  (  o.almox_padrao
                                                 + TRUNC (NVL (o.pendente, 0), 0)
                                                )
                                              - TRUNC (o.estq_seg)
                                             )
                                           - ROUND (NVL (o.demanda, 0))
                                          )
                                        / TRUNC (o.lote_mult)
                                       )
                             ELSE   (-1)
                                  * ROUND
                                       (  FLOOR
                                             (    ((  (  (  (  o.almox_padrao
                                                             + TRUNC
                                                                  (NVL
                                                                      (o.pendente,
                                                                       0
                                                                      ),
                                                                   0
                                                                  )
                                                            )
                                                          - TRUNC (o.estq_seg)
                                                         )
                                                       - ROUND (NVL (o.demanda,
                                                                     0)
                                                               )
                                                      )
                                                    / TRUNC (o.lote_mult)
                                                   )
                                                  )
                                                * 2
                                              + 1
                                             )
                                        / 2
                                       )
                          END
                        END
                  END      
             END
        INTO v_valor
        FROM FOCCO3I.TGAZIN_PMF o
       WHERE o.sessao = pi_sessao AND o.ID = pi_id;   
   END;
   
     /*select CASE WHEN v_valor < 0 THEN
               '{255-0-0/X-X-X/Tahoma-0-12}'||TO_CHAR(v_valor,'FM999G999G990D00')
            ELSE
               ''||TO_CHAR(v_valor,'FM999G999G990D00')
            END
       INTO v_resposta
       FROM DUAL;*/

   RETURN (v_valor);
END;
/

-- Cópia de OTIMIZA.FCALC_ESTQ_SEG (FUNCTION)
CREATE OR REPLACE FUNCTION GAZIN_PMF_CALC_ESTQ_SEG (
   pi_item   IN   NUMBER,
   pi_masc   IN   NUMBER
)
   RETURN NUMBER
IS
   v_total   NUMBER;
BEGIN
   IF pi_masc IS NULL THEN
      BEGIN
         SELECT CASE WHEN tgrp_invent.cod_grp_invent IN (110, 120) THEN 
                     CASE WHEN titens_empr.EMPR_ID = 1 THEN
                                CASE WHEN NVL (titens_planejamento.lote_mult, 0) >=
                                            (NVL((SELECT (SUM (mov.qtde) / 4)
                                                    FROM focco3i.tmov_estq mov,
                                                         focco3i.titens_estoque itest,
                                                         focco3i.titens_empr emp,
                                                         focco3i.tgrp_clas_ite grp,
                                                         focco3i.ttp_mov_estq tmov,
                                                         focco3i.titens ie
                                                   WHERE mov.itestq_id = itest.ID
                                                     AND itest.grp_clas_id = grp.ID
                                                     AND mov.tmves_id = tmov.ID
                                                     AND mov.ent_sai = 'S'
                                                     AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                    AND TRUNC (SYSDATE)
                                                     AND grp.cod_grp_ite LIKE '10%'
                                                     AND tmov.sigla IN ('REP', 'REQ', 'NFS', 'RSI')
                                                     AND itest.itempr_id = emp.ID
                                                     AND ie.ID = emp.item_id
                                                     AND ie.configurado = 0
                                                     AND emp.ID = titens_empr.ID),0)) THEN 
                                          NVL (titens_planejamento.lote_mult, 0)
                                ELSE   ROUND((NVL((SELECT (SUM (mov.qtde) / 4)
                                                          FROM focco3i.tmov_estq mov,
                                                               focco3i.titens_estoque itest,
                                                               focco3i.titens_empr emp,
                                                               focco3i.tgrp_clas_ite grp,
                                                               focco3i.ttp_mov_estq tmov,
                                                               focco3i.titens ie
                                                         WHERE mov.itestq_id = itest.ID
                                                           AND itest.grp_clas_id = grp.ID
                                                           AND mov.tmves_id = tmov.ID
                                                           AND mov.ent_sai = 'S'
                                                           AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                          AND TRUNC (SYSDATE)
                                                           AND grp.cod_grp_ite LIKE '10%'
                                                           AND tmov.sigla IN ('REP','REQ', 'NFS', 'RSI')
                                                           AND itest.itempr_id = emp.ID
                                                           AND ie.ID = emp.item_id
                                                           AND ie.configurado = 0
                                                           AND emp.ID = titens_empr.ID),0))
                                                / NVL (titens_planejamento.lote_mult,1)
                                               )
                                          * titens_planejamento.lote_mult
                                END
                     ELSE
                                CASE WHEN NVL (titens_planejamento.lote_mult, 0) >=
                                            (NVL((SELECT (SUM (mov.qtde) / 2)
                                                    FROM focco3i.tmov_estq mov,
                                                         focco3i.titens_estoque itest,
                                                         focco3i.titens_empr emp,
                                                         focco3i.tgrp_clas_ite grp,
                                                         focco3i.ttp_mov_estq tmov,
                                                         focco3i.titens ie
                                                   WHERE mov.itestq_id = itest.ID
                                                     AND itest.grp_clas_id = grp.ID
                                                     AND mov.tmves_id = tmov.ID
                                                     AND mov.ent_sai = 'S'
                                                     AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                    AND TRUNC (SYSDATE)
                                                     AND grp.cod_grp_ite LIKE '10%'
                                                     AND tmov.sigla IN ('REP', 'REQ', 'NFS', 'RSI')
                                                     AND itest.itempr_id = emp.ID
                                                     AND ie.ID = emp.item_id
                                                     AND ie.configurado = 0
                                                     AND emp.ID = titens_empr.ID),0)) THEN 
                                          NVL (titens_planejamento.lote_mult, 0)
                                ELSE   ROUND((NVL((SELECT (SUM (mov.qtde) / 2)
                                                          FROM focco3i.tmov_estq mov,
                                                               focco3i.titens_estoque itest,
                                                               focco3i.titens_empr emp,
                                                               focco3i.tgrp_clas_ite grp,
                                                               focco3i.ttp_mov_estq tmov,
                                                               focco3i.titens ie
                                                         WHERE mov.itestq_id = itest.ID
                                                           AND itest.grp_clas_id = grp.ID
                                                           AND mov.tmves_id = tmov.ID
                                                           AND mov.ent_sai = 'S'
                                                           AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                          AND TRUNC (SYSDATE)
                                                           AND grp.cod_grp_ite LIKE '10%'
                                                           AND tmov.sigla IN ('REP','REQ', 'NFS', 'RSI')
                                                           AND itest.itempr_id = emp.ID
                                                           AND ie.ID = emp.item_id
                                                           AND ie.configurado = 0
                                                           AND emp.ID = titens_empr.ID),0))
                                                / NVL (titens_planejamento.lote_mult,1)
                                               )
                                          * titens_planejamento.lote_mult
                                END
                     END           
                WHEN tgrp_invent.cod_grp_invent = 300 THEN 
                        CASE WHEN NVL (titens_planejamento.lote_mult, 0) >=
                                    (NVL((SELECT SUM (mov.qtde) / 3
                                            FROM focco3i.tmov_estq mov,
                                                 focco3i.titens_estoque itest,
                                                 focco3i.titens_empr emp,
                                                 focco3i.tgrp_clas_ite grp,
                                                 focco3i.ttp_mov_estq tmov
                                           WHERE mov.itestq_id = itest.ID
                                             AND itest.grp_clas_id = grp.ID
                                             AND mov.tmves_id = tmov.ID
                                             AND mov.ent_sai = 'S'
                                             AND mov.dt BETWEEN TRUNC (SYSDATE) - 90
                                                            AND TRUNC (SYSDATE)
                                             AND grp.cod_grp_ite LIKE '10%'
                                             AND tmov.sigla IN ('REP', 'REQ', 'NFS', 'RSI')
                                             AND itest.itempr_id = emp.ID
                                             AND emp.ID = titens_empr.ID),0))THEN 
                                    NVL (titens_planejamento.lote_mult, 0)
                             ELSE ROUND ((NVL ((SELECT SUM (mov.qtde) / 3
                                                  FROM focco3i.tmov_estq mov,
                                                       focco3i.titens_estoque itest,
                                                       focco3i.titens_empr emp,
                                                       focco3i.tgrp_clas_ite grp,
                                                       focco3i.ttp_mov_estq tmov
                                                 WHERE mov.itestq_id = itest.ID
                                                   AND itest.grp_clas_id = grp.ID
                                                   AND mov.tmves_id = tmov.ID
                                                   AND mov.ent_sai = 'S'
                                                   AND mov.dt BETWEEN TRUNC (SYSDATE) - 90
                                                                  AND TRUNC (SYSDATE)
                                                   AND grp.cod_grp_ite LIKE '10%'
                                                   AND tmov.sigla IN ('REP', 'REQ','NFS', 'RSI')
                                                   AND itest.itempr_id = emp.ID
                                                   AND emp.ID = titens_empr.ID),0))
                                        / NVL (titens_planejamento.lote_mult,1))
                                    * titens_planejamento.lote_mult
                          END
                   ELSE 999999
                END estq_seg
           INTO v_total
           FROM focco3i.titens_empr titens_empr,
                focco3i.titens titens,
                focco3i.titens_estoque titens_estoque,
                focco3i.tgrp_clas_ite tgrp_clas_ite,
                focco3i.titens_contabil titens_contabil,
                focco3i.tgrp_invent tgrp_invent,
                focco3i.titens_planejamento titens_planejamento
          WHERE titens.ID = titens_empr.item_id
            AND titens.configurado = 0
            AND titens_estoque.itempr_id = titens_empr.ID
            AND titens_estoque.grp_clas_id = tgrp_clas_ite.ID
            AND titens_empr.ID = titens_contabil.itempr_id
            AND tgrp_invent.ID = titens_contabil.grp_invent_id
            AND titens_empr.ID = titens_planejamento.itempr_id
            AND tgrp_clas_ite.cod_grp_ite LIKE '10%'
            AND titens_empr.ID = pi_item;
      EXCEPTION
         WHEN NO_DATA_FOUND
         THEN
            v_total := 0;
            RETURN (v_total);
      END;
      
   ELSE
   
      BEGIN
         SELECT CASE WHEN tgrp_invent.cod_grp_invent IN (110, 120 )THEN 
                       CASE WHEN TITENS_EMPR.EMPR_ID = 1 THEN
                             CASE WHEN NVL(titens_plan_conf.lote_mult, 0) >=
                                                                            (NVL((SELECT (SUM (mov.qtde) / 4)
                                                                                   FROM focco3i.tmov_estq mov,
                                                                                        focco3i.titens_estoque itest,
                                                                                        focco3i.titens_empr emp,
                                                                                        focco3i.tgrp_clas_ite grp,
                                                                                        focco3i.ttp_mov_estq tmov,
                                                                                        focco3i.titens ie,
                                                                                        focco3i.tmasc_item masc
                                                                                  WHERE mov.itestq_id = itest.ID
                                                                                    AND itest.grp_clas_id = grp.ID
                                                                                    AND mov.tmves_id = tmov.ID
                                                                                    AND mov.ent_sai = 'S'
                                                                                    AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                                                   AND TRUNC (SYSDATE)
                                                                                    AND grp.cod_grp_ite LIKE '10%'
                                                                                    AND tmov.sigla IN
                                                                                                 ('REP', 'REQ', 'NFS', 'RSI')
                                                                                    AND itest.itempr_id = emp.ID
                                                                                    AND ie.ID = emp.item_id
                                                                                    AND ie.configurado = 1
                                                                                    AND mov.tmasc_item_id = masc.ID
                                                                                    AND masc.ID = tmasc_item.ID),0)) THEN
                                         NVL(titens_plan_conf.lote_mult, 0)  
                             ELSE
                                    ROUND((NVL((SELECT (SUM (mov.qtde) / 4)
                                                   FROM focco3i.tmov_estq mov,
                                                        focco3i.titens_estoque itest,
                                                        focco3i.titens_empr emp,
                                                        focco3i.tgrp_clas_ite grp,
                                                        focco3i.ttp_mov_estq tmov,
                                                        focco3i.titens ie,
                                                        focco3i.tmasc_item masc
                                                  WHERE mov.itestq_id = itest.ID
                                                    AND itest.grp_clas_id = grp.ID
                                                    AND mov.tmves_id = tmov.ID
                                                    AND mov.ent_sai = 'S'
                                                    AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                   AND TRUNC (SYSDATE)
                                                    AND grp.cod_grp_ite LIKE '10%'
                                                    AND tmov.sigla IN
                                                                 ('REP', 'REQ', 'NFS', 'RSI')
                                                    AND itest.itempr_id = emp.ID
                                                    AND ie.ID = emp.item_id
                                                    AND ie.configurado = 1
                                                    AND mov.tmasc_item_id = masc.ID
                                                    AND masc.ID = tmasc_item.ID),0)) / NVL (titens_plan_conf.lote_mult,1))
                                                * titens_plan_conf.lote_mult
                             END                        
                       ELSE
                             CASE WHEN NVL(titens_plan_conf.lote_mult, 0) >=
                                                                            (NVL((SELECT (SUM (mov.qtde) / 2)
                                                                                   FROM focco3i.tmov_estq mov,
                                                                                        focco3i.titens_estoque itest,
                                                                                        focco3i.titens_empr emp,
                                                                                        focco3i.tgrp_clas_ite grp,
                                                                                        focco3i.ttp_mov_estq tmov,
                                                                                        focco3i.titens ie,
                                                                                        focco3i.tmasc_item masc
                                                                                  WHERE mov.itestq_id = itest.ID
                                                                                    AND itest.grp_clas_id = grp.ID
                                                                                    AND mov.tmves_id = tmov.ID
                                                                                    AND mov.ent_sai = 'S'
                                                                                    AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                                                   AND TRUNC (SYSDATE)
                                                                                    AND grp.cod_grp_ite LIKE '10%'
                                                                                    AND tmov.sigla IN
                                                                                                 ('REP', 'REQ', 'NFS', 'RSI')
                                                                                    AND itest.itempr_id = emp.ID
                                                                                    AND ie.ID = emp.item_id
                                                                                    AND ie.configurado = 1
                                                                                    AND mov.tmasc_item_id = masc.ID
                                                                                    AND masc.ID = tmasc_item.ID),0)) THEN
                                         NVL(titens_plan_conf.lote_mult, 0)  
                             ELSE
                                    ROUND((NVL((SELECT (SUM (mov.qtde) / 2)
                                                   FROM focco3i.tmov_estq mov,
                                                        focco3i.titens_estoque itest,
                                                        focco3i.titens_empr emp,
                                                        focco3i.tgrp_clas_ite grp,
                                                        focco3i.ttp_mov_estq tmov,
                                                        focco3i.titens ie,
                                                        focco3i.tmasc_item masc
                                                  WHERE mov.itestq_id = itest.ID
                                                    AND itest.grp_clas_id = grp.ID
                                                    AND mov.tmves_id = tmov.ID
                                                    AND mov.ent_sai = 'S'
                                                    AND mov.dt BETWEEN TRUNC (SYSDATE) - 30
                                                                   AND TRUNC (SYSDATE)
                                                    AND grp.cod_grp_ite LIKE '10%'
                                                    AND tmov.sigla IN
                                                                 ('REP', 'REQ', 'NFS', 'RSI')
                                                    AND itest.itempr_id = emp.ID
                                                    AND ie.ID = emp.item_id
                                                    AND ie.configurado = 1
                                                    AND mov.tmasc_item_id = masc.ID
                                                    AND masc.ID = tmasc_item.ID),0)) / NVL (titens_plan_conf.lote_mult,1))
                                                * titens_plan_conf.lote_mult
                             END
                       END                                                                           
                WHEN tgrp_invent.cod_grp_invent = 300 THEN 
                     CASE WHEN NVL (titens_planejamento.lote_mult, 0) >= (NVL((SELECT SUM (mov.qtde) / 3
                                                                               FROM focco3i.tmov_estq mov,
                                                                                    focco3i.titens_estoque itest,
                                                                                    focco3i.titens_empr emp,
                                                                                    focco3i.tgrp_clas_ite grp,
                                                                                    focco3i.ttp_mov_estq tmov
                                                                              WHERE mov.itestq_id = itest.ID
                                                                                AND itest.grp_clas_id = grp.ID
                                                                                AND mov.tmves_id = tmov.ID
                                                                                AND mov.ent_sai = 'S'
                                                                                AND mov.dt BETWEEN TRUNC (SYSDATE) - 90
                                                                                               AND TRUNC (SYSDATE)
                                                                                AND grp.cod_grp_ite LIKE '10%'
                                                                                AND tmov.sigla IN ('REP', 'REQ', 'NFS', 'RSI')
                                                                                AND itest.itempr_id = emp.ID
                                                                                AND emp.ID = titens_empr.ID
                                                                                AND mov.tmasc_item_id = tmasc_item.ID),0)) THEN
                              NVL(titens_plan_conf.lote_mult, 0)
                     ELSE
                            ROUND((NVL((SELECT SUM (mov.qtde) / 3
                                           FROM focco3i.tmov_estq mov,
                                                focco3i.titens_estoque itest,
                                                focco3i.titens_empr emp,
                                                focco3i.tgrp_clas_ite grp,
                                                focco3i.ttp_mov_estq tmov
                                          WHERE mov.itestq_id = itest.ID
                                            AND itest.grp_clas_id = grp.ID
                                            AND mov.tmves_id = tmov.ID
                                            AND mov.ent_sai = 'S'
                                            AND mov.dt BETWEEN TRUNC (SYSDATE) - 90
                                                           AND TRUNC (SYSDATE)
                                            AND grp.cod_grp_ite LIKE '10%'
                                            AND tmov.sigla IN
                                                         ('REP', 'REQ', 'NFS', 'RSI')
                                            AND itest.itempr_id = emp.ID
                                            AND emp.ID = titens_empr.ID
                                            AND mov.tmasc_item_id = tmasc_item.ID),0)) / NVL (titens_plan_conf.lote_mult,1))
                                        * titens_plan_conf.lote_mult
                     END                                                          
             ELSE 999999             
             END estq_seg
           INTO v_total
           FROM focco3i.titens_empr titens_empr,
                focco3i.titens titens,
                focco3i.titens_estoque titens_estoque,
                focco3i.tgrp_clas_ite tgrp_clas_ite,
                --focco3i.titens_estq_conf titens_estq_conf,
                focco3i.tmasc_item tmasc_item,
                focco3i.titens_contabil titens_contabil,
                focco3i.tgrp_invent tgrp_invent,
                FOCCO3I.titens_planejamento titens_planejamento,
                FOCCO3I.titens_plan_conf titens_plan_conf
          WHERE titens.ID = titens_empr.item_id
            AND titens.configurado = 1
            AND titens_estoque.itempr_id = titens_empr.ID
            AND titens_estoque.grp_clas_id = tgrp_clas_ite.ID
            --AND titens_estoque.ID = titens_estq_conf.itestq_id
            --AND tmasc_item.ID = titens_estq_conf.tmasc_item_id
            AND TITENS_EMPR.ID = titens_planejamento.ITEMPR_ID
            AND TITENS_PLANEJAMENTO.ID = TITENS_PLAN_CONF.ITPL_ID
            AND TMASC_ITEM.ID =  TITENS_PLAN_CONF.TMASC_ITEM_ID                  
            AND titens_empr.ID = titens_contabil.itempr_id
            AND tgrp_invent.ID = titens_contabil.grp_invent_id
            AND tgrp_clas_ite.cod_grp_ite LIKE '10%'
            AND tmasc_item.sit = 1
            AND tmasc_item.ID = pi_masc;
      EXCEPTION
         WHEN NO_DATA_FOUND
         THEN
            v_total := 0;
            RETURN (v_total);
      END;
      
   END IF;

   RETURN (v_total);
END;
/

-- Cópia de FOCCO3I.GERA_INTERFABRICA (PROCEDURE)
CREATE OR REPLACE PROCEDURE GAZIN_PMF_GERA_INTERFABRICA (p_sessao IN VARCHAR2)
IS
   PRAGMA AUTONOMOUS_TRANSACTION;

   bErro_Capa BOOLEAN;
   bErro_Item BOOLEAN;

   --- VARIAVEIS DO PDV
   v_pedido_id      NUMBER;
   v_itpdv_id       NUMBER;
   v_num_pedido     NUMBER;
   v_empr_id        NUMBER;
   v_cli_id         NUMBER;
   v_est_fat        NUMBER;
   v_cnpj           NUMBER;
   v_itcm_id        NUMBER;

   vgrp_invent      NUMBER;
   vTP_nf           NUMBER;
   v_empr_prod_id   NUMBER;
   po_erro          VARCHAR2 (4000);

   vvlr_bruto number;
   vvlr_liq number;
   vvlr_liq_ipi number;
-----

   --- VARIAVEIS DO PDC
   v_pdc_id        NUMBER;
   v_itpdc_id      NUMBER;
   v_num_PDC       NUMBER;
   v_FORN_id       NUMBER; 
   v_custo_med     number;
   v_itempr_id_prod     number;
   v_tmasc_item_id_prod number;
   v_achou              BOOLEAN; 


BEGIN

 bErro_Capa:=false ;


  FOR rcur IN (  SELECT (SELECT empr_prod_id
                           FROM focco3i.titens_engenharia
                          WHERE itempr_id = o.item_empr_id) empr_prod_id, itestq.unid_med_id,
                        itsup.ID itsup_id, IT.DESC_TECNICA, o.*
                   FROM FOCCO3I.TGAZIN_PMF o,
                        focco3i.titens_estoque itestq,
                        focco3i.titens_suprimentos itsup,
                        focco3i.titens_empr itempr,
                        focco3i.titens it
                  WHERE o.item_empr_id = itestq.itempr_id
                    AND itestq.itempr_id = itsup.itempr_id
                    AND itsup.itempr_id = itempr.ID
                    AND itempr.item_id = it.ID
                    AND sessao =   p_sessao
                    AND selecao = 1
                    AND o.qtde_alt > 0
                ORDER BY 1 )
   LOOP


      -----------------------------------------
      --           GERAR PDV
      -----------------------------------------

      BEGIN

         v_empr_id := rcur.empr_id;



         -- CASO A EMPRESA PRODUTORA SEJA DIFERENTE GERA UMA NOVA CAPA DE PDV E PDC
         IF NVL (v_empr_prod_id, 0) <> NVL (rcur.empr_prod_id, 99)  AND bErro_Capa = False THEN

             BEGIN  

                 IF V_PEDIDO_ID  IS NOT NULL THEN 

                     SELECT  sum(QTDE*VLR_BRUTO) , sum(QTDE*VLR_LIQ), sum(QTDE*VLR_LIQ_IPI)
                       INTO  VVLR_BRUTO , VVLR_LIQ, VVLR_LIQ_IPI
                       FROM TITENS_PDV 
                       WHERE PDV_ID = V_PEDIDO_ID ;

                     UPDATE TPEDIDOS_VENDA
                        SET  VLR_BRUTO = VVLR_BRUTO , VLR_LIQ = VVLR_LIQ   ,  VLR_LIQ_IPI= VVLR_LIQ_IPI
                      WHERE ID  = V_PEDIDO_ID ;

                     COMMIT;                 

                 END IF;

                 v_empr_prod_id := rcur.empr_prod_id;

                 SELECT emp.cnpj 
                    INTO v_cnpj
                    FROM tempresas emp
                   WHERE  emp.ID = rcur.empr_id  ;


                  SELECT est.ID est_id, empcli.cli_id
                    INTO v_est_fat, v_cli_id
                    FROM testabelecimentos est, temp_est em, temp_cli empcli
                   WHERE est.cnpj = v_cnpj
                     AND est.ID = em.est_id
                     AND em.empcli_id = empcli.ID 
                     AND em.ativo = 1 and rownum=1 ;


                   ---- CAPA DO PDC
                  SELECT (SELECT ID
                            FROM tfornecedores
                           WHERE cnpj = emp.cnpj AND ativo = 1) tforn_id
                    INTO v_forn_id
                    FROM tempresas emp
                   WHERE emp.ID = v_empr_prod_id   ;             

                  SUP_PDC_CRIA_CAPA_PED_COMPRA (   v_empr_id ,
                                                   v_forn_id ,  
                                                   'OCI'     ,
                                                   NULL      ,
                                                   v_pdc_id  ,
                                                   v_num_PDC ,
                                                   PO_erro,
                                                   null,
                                                   null,
                                                   null,
                                                   null,
                                                   null,
                                                   null,
                                                   null,
                                                   null,
                                                   'Gerado a partir da INTEFABRICA OTM ' );             


             ---- CAPA DO PDV

                  SELECT seq_id_tpedidos_venda.NEXTVAL    INTO v_pedido_id  FROM DUAL;

                  v_num_pedido :=   focco3i_pdv.retorna_num_pedido (p_empr_id      => rcur.empr_prod_id,
                                                                    p_id           => v_pedido_id,
                                                                    p_tipo         => 'PDV',
                                                                    p_erro         => po_erro
                                                                   );

              --   salva_wpesquisa ('MANOLOP', 1, 'NUM:' || v_num_pedido);
                  focco3i_pdv.inclui_capa_pdv (p_id               => v_pedido_id,
                                               p_num_pedido       => v_num_pedido,
                                               p_erro             => po_erro,
                                               p_empr_id          => rcur.empr_prod_id,
                                               p_cli_id           => v_cli_id,
                                               p_origem_pdv       => 'F',
                                               p_dt_entrega       => SYSDATE+10,
                                               p_ordem_cpr        => v_empr_id || '-' || v_num_PDC,
                                               p_est_id_fat       => v_est_fat,
                                               p_est_id_entr      => v_est_fat,
                                               p_est_id_cbr       => v_est_fat
                                              );


              --msgs 
              Insert into FOCCO3I.TGAZIN_PMF_MSG  (sessao,   tipo,     msg,  numero, empr_id, dt_msg)
                                         Values  (p_sessao, 'PDC', 'Sucesso'  ,v_num_PDC , v_empr_id,   SYSDATE );                                  
              Insert into FOCCO3I.TGAZIN_PMF_MSG  (sessao,   tipo,     msg,  numero, empr_id, dt_msg)
                                         Values  (p_sessao, 'PDV', 'Sucesso'  , v_num_pedido, rcur.empr_prod_id,   SYSDATE );       


              FOCCO3I_ANALISE_COM.ANALISE_COMERCIAL(v_pedido_id, 'PDV');
              FOCCO3I_ANALISE_FIN.ANALISE_FINANCEIRA(v_pedido_id,'PDV');                                                


             EXCEPTION 
               WHEN OTHERS THEN 
                   Insert into FOCCO3I.TGAZIN_PMF_MSG  
                               (sessao,   tipo,     msg,  numero, empr_id, dt_msg)
                       Values  (p_sessao, 'PDV', 'Erro ao inserir capa PDV/PDC:' || po_erro, null, null,   SYSDATE ); 

                  bErro_Capa:= true ;
             END;                               

         END IF;

 -- salva_wpesquisa('MANOLOP',7,1);  

         IF NOT(bErro_Capa) THEN 

 --  salva_wpesquisa('MANOLOP',8,1);                
            ---------------------------------
            --INCLUSAO DOS ITENS PDV E PDC
            ---------------------------------             
             --Retorna o custo medio do item na empresa produtora.
               MAN_ENG_RETORNA_ITEM_EQUIV( rcur.item_empr_id
                                         , rcur.masc_id
                                         , v_empr_prod_id  
                                         , v_itempr_id_prod
                                         , v_tmasc_item_id_prod
                                         , v_achou
                                         );
 -- salva_wpesquisa('MANOLOP',9,1);           

            IF v_achou THEN


                  BEGIN 

  -- salva_wpesquisa('MANOLOP',10,1);  
                        v_custo_med :=  MAN_EST_RETORNA_CUSTO_MEDIO ( v_empr_prod_id
                                                                    , v_itempr_id_prod
                                                                    , null
                                                                    , 1
                                                                    , SYSDATE
                                                                    , 999999999999
                                                                    , v_tmasc_item_id_prod);


                         -- localiza o id item comercial da empresa produtora
                        SELECT   itcta.GRP_INVENT_ID
                          INTO   vgrp_invent
                          FROM titens_comercial itcm, titens_contabil itcta
                         WHERE itcm.itempr_id = itcta.itempr_id 
                         and itcm.itempr_id = RCUR.item_empr_id;



                         --GRP Invent = 300                                                              
                         if vgrp_invent = 174    and  v_empr_id <> 1 THEN 
                             vTP_nf   := 1594;
                         ELSif vgrp_invent <> 174 and  v_empr_id IN (3,5,7) THEN 
                             vTP_nf   := 1495;
                         ELSif vgrp_invent <> 174 and  v_empr_id IN (2,4,8,9,13) THEN 
                             vTP_nf   := 3343;                             
                         ELSE    
                             vTP_nf:=NULL;
                         END IF;                                            


                       --INCLUI ITENS PDC 

                       SUP_PDC_CRIA_ITEM_PED_COMPRA
                                ( v_empr_id,
                                  rcur.qtde_alt,--QTDE
                                  rcur.qtde_alt,         -- quantidade corrigida
                                  v_custo_med,                 -- valor unitário
                                  RCUR.UNID_MED_ID,                  -- unidade de medida
                                  SYSDATE+10,            -- data de entrega
                                  RCUR.DESC_TECNICA ,                   -- descrição do item
                                  RCUR.MASC_ID,                      -- id da máscara
                                  NULL,                      -- plano
                                  NULL,                      -- número da ordem
                                  RCUR.itsup_id,                    -- id de item suprimento
                                  v_pdc_id       ,     -- id do pedido de compra
                                  null,                      -- id da tabela tfordesc_itens
                                  PO_erro         ,           -- erro (retorna)
                                  v_itpdc_id ,
                                  NULL,
                                  NULL,
                                  NULL,
                                  NULL,
                                  vTP_nf,
                                  NULL,
                                  'Gerado a partir da INTEFABRICA OTM');                                                  


                         -- localiza o id item comercial da empresa produtora
                        SELECT itcm.ID, itcta.GRP_INVENT_ID
                          INTO v_itcm_id, vgrp_invent
                          FROM titens_comercial itcm, titens_contabil itcta
                         WHERE itcm.itempr_id = itcta.itempr_id 
                         and itcm.itempr_id = v_itempr_id_prod;


                         --GRP Invent = 300                                                              
                         if vgrp_invent = 174 and  v_empr_id <> 1 THEN 
                             vTP_nf   := 3185;
                         ELSE
                             vTP_nf:=NULL;
                         END IF;


                        FOCCO3I_PDV.INCLUI_ITEM_PDV (p_id                 => v_itpdv_id,
                                                     p_erro               => po_erro,
                                                     p_pdv_id             => v_pedido_id,
                                                     p_qtde               => rcur.qtde_alt,
                                                     p_itcm_id            => v_itcm_id,
                                                     p_tmasc_item_id      => v_tmasc_item_id_prod,
                                                     p_dt_entrega         => SYSDATE +10,
                                                     p_preco_unit         => v_custo_med,
                                                     p_unid_med_id        => NULL,
                                                     p_almox_id           => NULL,
                                                     p_firme              => NULL,
                                                     p_tpnf_id            => vTP_nf,
                                                     p_clas_fisc_id       => NULL,                                                                    
                                                     pi_cons_fat          => NULL, 
                                                     pi_cons_peso         => NULL,
                                                     p_vlr_liq            => v_custo_med
                                                    --PI_CONS_PESO
                                                    );


   --salva_wpesquisa('MANOLOP',14,1);                  

                  COMMIT;

                  update titens_pdv set vlr_ipi=0, vlr_bruto = v_custo_med, vlr_liq=v_custo_med, vlr_liq_ipi=v_custo_med where id =v_itpdv_id;

                  COMMIT;

  -- salva_wpesquisa('MANOLOP',15,1);                   
                  EXCEPTION 
                     WHEN OTHERS THEN 
                         ROLLBACK;
                         bErro_Item := TRUE  ; 

                         Insert into FOCCO3I.TGAZIN_PMF_MSG  
                                     (sessao,   tipo,     msg,  numero, empr_id, dt_msg)
                             Values  (p_sessao, 'ERRO', 'Erro ao inserir ITEM PDV/PDC:' || po_erro, null, null,   SYSDATE ); 



                  END;         

               ELSE
                --- inserir msg de erro 
                -----------------------------------------------
                   Insert into FOCCO3I.TGAZIN_PMF_MSG  
                               (sessao,   tipo,     msg,  numero, empr_id, dt_msg)
                       Values  (p_sessao, 'ERRO', 'Erro ao inserir ITEM PDV/PDC nao localizou item de referente na empresa destino item:' || rcur.cod_item  || ' Masc. Id:' || rcur.masc_id  , null, null,   SYSDATE ); 

               END IF;

         END IF;                          

      END;

   END LOOP;

   COMMIT;
  --  salva_wpesquisa('MANOLOP',16,1);  

  IF V_PEDIDO_ID  IS NOT NULL THEN 

      SELECT  sum(QTDE*VLR_BRUTO) , sum(QTDE*VLR_LIQ), sum(QTDE*VLR_LIQ_IPI)
        INTO  VVLR_BRUTO , VVLR_LIQ, VVLR_LIQ_IPI
        FROM TITENS_PDV 
        WHERE PDV_ID = V_PEDIDO_ID ;

      UPDATE TPEDIDOS_VENDA
         SET  VLR_BRUTO = VVLR_BRUTO , VLR_LIQ = VVLR_LIQ   ,  VLR_LIQ_IPI= VVLR_LIQ_IPI
       WHERE ID = V_PEDIDO_ID ;

      COMMIT;                 


  END IF;

  ---RECALCULAR PEDIDO -> LUCAS 12/04/2024
  --FOCCO3I.COM_PDV_RECALCULA_PEDIDO(PDV_ID)

   --salva_wpesquisa('MANOLOP',17,1);  

  ROLLBACK; 

END;
/

-- Parâmetros de almoxarifado por filial (cópia de OTIMIZA.TPARS_ALMOX_OTM em 04/11/2026)
DELETE FROM FOCCO3I.TGAZIN_PMF_PARS_ALMOX
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (1, 1, '4', NULL, NULL, '1,4,7,8,9,97,99,101,102,103,105,106,9403,9405')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (2, 2, '4,73,98', NULL, NULL, '4,73,97,98,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (3, 3, '4,73', NULL, NULL, '4,73,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (4, 4, '4', NULL, NULL, '4,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (5, 5, '4', NULL, NULL, '4,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (6, 6, '4', NULL, NULL, '4,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (7, 7, '4', NULL, NULL, '4,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (8, 8, '4', NULL, NULL, '4,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (9, 9, '4', NULL, NULL, '4,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (10, 13, '4,73', NULL, NULL, '4,97,101,102,103,105,106')
/
INSERT INTO FOCCO3I.TGAZIN_PMF_PARS_ALMOX (ID, EMPR_ID, ALMOX_PAD_IGUAL, ALMOX_PAD_DIF, OUTRS_ALMOX_IGUAL, OUTRS_ALMOX_DIF) VALUES (11, 14, '4,73', NULL, NULL, '4,97,101,102,103,105,106')
/
COMMIT
/
