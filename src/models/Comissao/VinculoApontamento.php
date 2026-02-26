<?php

namespace src\models\Comissao;

use core\Database;
use PDO;
use Exception;

/**
 * Model para Vínculo de Apontamentos sem Recurso
 * 
 * Tabela: FOCCO3I.TGAZIN_VINC_APONTAMENTO
 * 
 * Regras:
 * - Lista apontamentos que não possuem RECURSO_ID
 * - Permite vinculação manual a um funcionário
 * - Registra usuário que vinculou e data da vinculação
 * - Não permite alteração se o apontamento estiver fechado
 * - Apenas apontamentos vinculados entram no cálculo da comissão
 */
class VinculoApontamento
{
    /**
     * Listar apontamentos sem recurso vinculado
     * @param array $filtros
     * @return array
     */
    public function listarApontamentosSemRecurso($filtros = [])
    {
        $pdo = Database::getInstance('focco');

        // Query simplificada: apontamentos que NÃO possuem registro em TORD_MOV_FAB_MAQ
        $sql = "SELECT 
                    TM.ID AS APONTAMENTO_ID,
                    TM.DT_APONT AS DATA_APONTAMENTO,
                    TO_CHAR(TM.DT_APONT, 'DD/MM/YYYY') AS DT_APONT_FMT,
                    TM.QUANTIDADE,
                    O.ID AS ID_ORDEM,
                    O.NUM_ORDEM AS NR_ORDEM,
                    I.COD_ITEM,
                    I.DESC_TECNICA AS DESC_ITEM,
                    MI.ID AS ID_MASCARA,
                    MI.MASCARA,
                    CT.ID AS ID_CENTRO_TRAB,
                    CT.COD_CENTRO,
                    CT.DESCRICAO AS DESC_CENTRO
                FROM FOCCO3I.TORDENS_MOVTO TM
                INNER JOIN FOCCO3I.TORDENS_ROT TR ON TR.ID = TM.TORDEN_ROT_ID
                INNER JOIN FOCCO3I.TORDENS O ON O.ID = TR.ORDEM_ID
                INNER JOIN FOCCO3I.TITENS_PLANEJAMENTO TP ON TP.ID = O.ITPL_ID
                INNER JOIN FOCCO3I.TITENS_EMPR IE ON IE.ID = TP.ITEMPR_ID
                INNER JOIN FOCCO3I.TITENS I ON I.ID = IE.ITEM_ID
                INNER JOIN FOCCO3I.TCENTROS_TRAB CT ON CT.ID = TR.CENTR_TRAB_ID
                LEFT JOIN FOCCO3I.TMASC_ITEM MI ON MI.ID = O.TMASC_ITEM_ID
                WHERE TR.APONTAMENTO = 1
                AND TR.OBRIGATORIO = 1
                AND NOT EXISTS (
                    SELECT 1 FROM FOCCO3I.TORD_MOV_FAB_MAQ MQ 
                    WHERE MQ.ORDEM_MOVT_ID = TM.ID
                )";

        $params = [];

        if (!empty($filtros['id_empr'])) {
            $sql .= " AND O.EMPR_ID = :id_empr";
            $params[':id_empr'] = $filtros['id_empr'];
        }

        if (!empty($filtros['dt_inicio'])) {
            $sql .= " AND TM.DT_APONT >= TO_DATE(:dt_inicio, 'YYYY-MM-DD')";
            $params[':dt_inicio'] = $filtros['dt_inicio'];
        }

        if (!empty($filtros['dt_fim'])) {
            $sql .= " AND TM.DT_APONT < TO_DATE(:dt_fim, 'YYYY-MM-DD') + 1";
            $params[':dt_fim'] = $filtros['dt_fim'];
        }

        if (!empty($filtros['id_centro_trab'])) {
            $sql .= " AND TR.CENTR_TRAB_ID = :id_centro_trab";
            $params[':id_centro_trab'] = $filtros['id_centro_trab'];
        }

        $sql .= " ORDER BY TM.DT_APONT DESC, I.DESC_TECNICA";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vincular recurso (máquina) ao apontamento
     * Insere registro na TORD_MOV_FAB_MAQ
     * @param int $apontamentoId ID do movimento da ordem (TORDENS_MOVTO.ID)
     * @param int $recursoId ID da máquina/recurso (TMAQUINAS.ID)
     * @return bool
     */
    public function vincularRecurso($apontamentoId, $recursoId)
    {
        $pdo = Database::getInstance('focco');
        
        // Verificar se já existe
        $sqlCheck = "SELECT COUNT(*) FROM FOCCO3I.TORD_MOV_FAB_MAQ WHERE ORDEM_MOVT_ID = :apt_id";
        $stmtCheck = $pdo->prepare($sqlCheck);
        $stmtCheck->bindParam(':apt_id', $apontamentoId, PDO::PARAM_INT);
        $stmtCheck->execute();
        
        if ($stmtCheck->fetchColumn() > 0) {
            throw new Exception('Este apontamento já possui um recurso vinculado');
        }
        
        // Buscar próximo ID (MAX + 1, pois a tabela pode não ter sequence)
        $sqlMax = "SELECT NVL(MAX(ID), 0) + 1 FROM FOCCO3I.TORD_MOV_FAB_MAQ";
        $stmtMax = $pdo->query($sqlMax);
        $nextId = $stmtMax->fetchColumn();
        
        $sql = "INSERT INTO FOCCO3I.TORD_MOV_FAB_MAQ (ID, ORDEM_MOVT_ID, MAQUINA_ID) VALUES (:id, :apt_id, :rec_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $nextId, PDO::PARAM_INT);
        $stmt->bindParam(':apt_id', $apontamentoId, PDO::PARAM_INT);
        $stmt->bindParam(':rec_id', $recursoId, PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Vincular apontamento a funcionário
     * @param array $dados
     * @return int ID do vínculo
     */
    public function vincular($dados)
    {
        // Verificar se já existe vínculo para este apontamento
        if ($this->verificarVinculoExistente($dados['id_apontamento'])) {
            throw new Exception('Este apontamento já possui um vínculo');
        }

        $sql = "INSERT INTO FOCCO3I.TGAZIN_VINC_APONTAMENTO (
                    ID_EMPR,
                    ID_APONTAMENTO,
                    ID_FUNCIONARIO,
                    ID_RECURSO,
                    FECHADO,
                    DT_VINCULACAO,
                    ID_USUARIO_VINC,
                    OBSERVACAO
                ) VALUES (
                    :id_empr,
                    :id_apontamento,
                    :id_funcionario,
                    :id_recurso,
                    'N',
                    SYSDATE,
                    :id_usuario,
                    :observacao
                )";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_empr', $dados['id_empr'], PDO::PARAM_INT);
        $stmt->bindParam(':id_apontamento', $dados['id_apontamento'], PDO::PARAM_INT);
        $stmt->bindParam(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmt->bindParam(':id_recurso', $dados['id_recurso'], PDO::PARAM_INT);
        $stmt->bindParam(':id_usuario', $dados['id_usuario'], PDO::PARAM_INT);
        $obs = $dados['observacao'] ?? null;
        $stmt->bindParam(':observacao', $obs, PDO::PARAM_STR);

        $stmt->execute();

        // Buscar o ID inserido (tabela usa IDENTITY, não sequence)
        $sqlId = "SELECT ID_VINC_APT FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO 
                  WHERE ID_APONTAMENTO = :id_apontamento";
        $stmtId = $pdo->prepare($sqlId);
        $stmtId->bindParam(':id_apontamento', $dados['id_apontamento'], PDO::PARAM_INT);
        $stmtId->execute();
        $id = $stmtId->fetchColumn();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_VINC_APONTAMENTO', $id, 'I', null, $dados, $dados['id_usuario']);

        return $id;
    }

    /**
     * Verificar se já existe vínculo para um apontamento
     * @param int $idApontamento
     * @return bool
     */
    public function verificarVinculoExistente($idApontamento)
    {
        $sql = "SELECT COUNT(*) FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO
                WHERE ID_APONTAMENTO = :id_apontamento";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_apontamento', $idApontamento, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Buscar vínculo por ID
     * @param int $id
     * @return array|null
     */
    public function buscarPorId($id)
    {
        $sql = "SELECT 
                    VA.ID_VINC_APT,
                    VA.ID_EMPR,
                    VA.ID_APONTAMENTO,
                    VA.ID_FUNCIONARIO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNCIONARIO,
                    VA.ID_RECURSO,
                    VA.FECHADO,
                    TO_CHAR(VA.DT_VINCULACAO, 'DD/MM/YYYY HH24:MI') AS DT_VINCULACAO,
                    VA.ID_USUARIO_VINC,
                    VA.OBSERVACAO
                FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO VA
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = VA.ID_FUNCIONARIO
                WHERE VA.ID_VINC_APT = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar vínculo por ID do apontamento
     * @param int $idApontamento
     * @return array|null
     */
    public function buscarPorApontamento($idApontamento)
    {
        $sql = "SELECT 
                    VA.ID_VINC_APT,
                    VA.ID_EMPR,
                    VA.ID_APONTAMENTO,
                    VA.ID_FUNCIONARIO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNCIONARIO,
                    VA.ID_RECURSO,
                    VA.FECHADO,
                    VA.DT_VINCULACAO,
                    VA.ID_USUARIO_VINC
                FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO VA
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = VA.ID_FUNCIONARIO
                WHERE VA.ID_APONTAMENTO = :id_apontamento";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id_apontamento', $idApontamento, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Listar vínculos existentes por período
     * @param array $filtros
     * @return array
     */
    public function listarVinculos($filtros = [])
    {
        $pdo = Database::getInstance('focco');

        $sql = "SELECT 
                    VA.ID_VINC_APT AS ID,
                    VA.ID_APONTAMENTO,
                    VA.ID_FUNCIONARIO,
                    F.COD_FUNC,
                    F.NOME AS NOME_FUNC,
                    TM.DT_APONT,
                    TM.QUANTIDADE,
                    O.NUM_ORDEM AS NR_ORDEM,
                    I.DESC_TECNICA AS DESC_ITEM,
                    VA.DT_VINCULACAO AS DT_VINCULO,
                    VA.OBSERVACAO,
                    VA.FECHADO
                FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO VA
                INNER JOIN FOCCO3I.TFUNCIONARIOS F ON F.ID = VA.ID_FUNCIONARIO
                LEFT JOIN FOCCO3I.TORDENS_MOVTO TM ON TM.ID = VA.ID_APONTAMENTO
                LEFT JOIN FOCCO3I.TORDENS_ROT TR ON TR.ID = TM.TORDEN_ROT_ID
                LEFT JOIN FOCCO3I.TORDENS O ON O.ID = TR.ORDEM_ID
                LEFT JOIN FOCCO3I.TITENS_PLANEJAMENTO TP ON TP.ID = O.ITPL_ID
                LEFT JOIN FOCCO3I.TITENS_EMPR IE ON IE.ID = TP.ITEMPR_ID
                LEFT JOIN FOCCO3I.TITENS I ON I.ID = IE.ITEM_ID
                WHERE 1=1";

        $params = [];

        if (!empty($filtros['id_empr'])) {
            $sql .= " AND VA.ID_EMPR = :id_empr";
            $params[':id_empr'] = $filtros['id_empr'];
        }

        if (!empty($filtros['dt_inicio'])) {
            $sql .= " AND VA.DT_VINCULACAO >= TO_DATE(:dt_inicio, 'YYYY-MM-DD')";
            $params[':dt_inicio'] = $filtros['dt_inicio'];
        }

        if (!empty($filtros['dt_fim'])) {
            $sql .= " AND VA.DT_VINCULACAO < TO_DATE(:dt_fim, 'YYYY-MM-DD') + 1";
            $params[':dt_fim'] = $filtros['dt_fim'];
        }

        $sql .= " ORDER BY VA.DT_VINCULACAO DESC";

        $stmt = $pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Atualizar vínculo
     * @param int $id
     * @param array $dados
     * @return bool
     */
    public function atualizar($id, $dados)
    {
        // Buscar dados anteriores
        $dadosAnteriores = $this->buscarPorId($id);

        // Verificar se está fechado
        if ($dadosAnteriores && $dadosAnteriores['FECHADO'] === 'S') {
            throw new Exception('Este vínculo está fechado e não pode ser alterado');
        }

        $sql = "UPDATE FOCCO3I.TGAZIN_VINC_APONTAMENTO SET
                    ID_FUNCIONARIO = :id_funcionario,
                    ID_RECURSO = :id_recurso,
                    OBSERVACAO = :observacao,
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_VINC_APT = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmt->bindParam(':id_recurso', $dados['id_recurso'], PDO::PARAM_INT);
        $obs = $dados['observacao'] ?? null;
        $stmt->bindParam(':observacao', $obs, PDO::PARAM_STR);
        $stmt->bindParam(':id_usuario', $dados['id_usuario'], PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_VINC_APONTAMENTO', $id, 'U', $dadosAnteriores, $dados, $dados['id_usuario']);

        return $result;
    }

    /**
     * Excluir vínculo
     * @param int $id
     * @param int $usuId
     * @return bool
     */
    public function excluir($id, $usuId)
    {
        // Buscar dados anteriores
        $dadosAnteriores = $this->buscarPorId($id);

        // Verificar se está fechado
        if ($dadosAnteriores && $dadosAnteriores['FECHADO'] === 'S') {
            throw new Exception('Este vínculo está fechado e não pode ser excluído');
        }

        $sql = "DELETE FROM FOCCO3I.TGAZIN_VINC_APONTAMENTO WHERE ID_VINC_APT = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        $result = $stmt->execute();

        // Registrar log de auditoria
        $this->registrarLog('TGAZIN_VINC_APONTAMENTO', $id, 'D', $dadosAnteriores, null, $usuId);

        return $result;
    }

    /**
     * Fechar vínculo (não permite mais alterações)
     * @param int $id
     * @param int $usuId
     * @return bool
     */
    public function fechar($id, $usuId)
    {
        $sql = "UPDATE FOCCO3I.TGAZIN_VINC_APONTAMENTO SET
                    FECHADO = 'S',
                    DT_ALTERACAO = SYSDATE,
                    ID_USUARIO_ALT = :id_usuario
                WHERE ID_VINC_APT = :id";

        $pdo = Database::getInstance('focco');
        $stmt = $pdo->prepare($sql);

        $stmt->bindParam(':id_usuario', $usuId, PDO::PARAM_INT);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Vincular múltiplos apontamentos de uma vez
     * @param array $apontamentos Lista de IDs de apontamentos
     * @param int $funcId ID do funcionário
     * @param int $recursoId ID do recurso (opcional)
     * @param int $emprId ID da empresa
     * @param int $usuId ID do usuário
     * @return array Resultado [sucesso, erros]
     */
    public function vincularEmLote($apontamentos, $funcId, $recursoId, $emprId, $usuId)
    {
        $sucesso = [];
        $erros = [];

        foreach ($apontamentos as $aptId) {
            try {
                $id = $this->vincular([
                    'id_empr' => $emprId,
                    'id_apontamento' => $aptId,
                    'id_funcionario' => $funcId,
                    'id_recurso' => $recursoId,
                    'id_usuario' => $usuId
                ]);
                $sucesso[] = ['id_apontamento' => $aptId, 'id_vinculo' => $id];
            } catch (Exception $e) {
                $erros[] = ['id_apontamento' => $aptId, 'erro' => $e->getMessage()];
            }
        }

        return [
            'sucesso' => $sucesso,
            'erros' => $erros,
            'total_sucesso' => count($sucesso),
            'total_erros' => count($erros)
        ];
    }

    /**
     * Registrar log de auditoria
     */
    private function registrarLog($tabela, $idRegistro, $operacao, $dadosAnteriores, $dadosNovos, $usuId)
    {
        try {
            $sql = "INSERT INTO FOCCO3I.TGAZIN_LOG_AUDITORIA (
                        ID_EMPR,
                        TABELA,
                        ID_REGISTRO,
                        OPERACAO,
                        DADOS_ANTERIORES,
                        DADOS_NOVOS,
                        DT_OPERACAO,
                        ID_USUARIO,
                        IP_USUARIO
                    ) VALUES (
                        :id_empr,
                        :tabela,
                        :id_registro,
                        :operacao,
                        :dados_anteriores,
                        :dados_novos,
                        SYSDATE,
                        :id_usuario,
                        :ip_usuario
                    )";

            $pdo = Database::getInstance('focco');
            $stmt = $pdo->prepare($sql);

            $emprId = $_SESSION['empresa']['id'] ?? null;
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $dadosAntJson = $dadosAnteriores ? json_encode($dadosAnteriores) : null;
            $dadosNovJson = $dadosNovos ? json_encode($dadosNovos) : null;

            $stmt->bindParam(':id_empr', $emprId, PDO::PARAM_INT);
            $stmt->bindParam(':tabela', $tabela, PDO::PARAM_STR);
            $stmt->bindParam(':id_registro', $idRegistro, PDO::PARAM_INT);
            $stmt->bindParam(':operacao', $operacao, PDO::PARAM_STR);
            $stmt->bindParam(':dados_anteriores', $dadosAntJson, PDO::PARAM_STR);
            $stmt->bindParam(':dados_novos', $dadosNovJson, PDO::PARAM_STR);
            $stmt->bindParam(':id_usuario', $usuId, PDO::PARAM_INT);
            $stmt->bindParam(':ip_usuario', $ip, PDO::PARAM_STR);

            $stmt->execute();
        } catch (\Exception $e) {
            // Log de erro silenciado - não interrompe a operação principal
        }
    }
}


