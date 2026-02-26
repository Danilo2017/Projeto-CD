<?php

namespace src\models\Comissao;

use core\Database;
use PDO;

/**
 * Model para Regras Específicas de Comissão por Funcionário
 * Tabela: FOCCO3I.TGAZIN_REGRA_FUNC
 */
class RegraFuncionario
{
    // Tipos de comissão
    const TIPO_VALOR_FIXO = 'V';       // Valor fixo por UP/ponto (multiplica pela produção)
    const TIPO_PERCENTUAL = 'P';        // Percentual sobre pontos
    const TIPO_FIXO_TOTAL = 'F';        // Valor fixo total (não multiplica pela produção)
    const TIPO_MISTO = 'M';             // Valor fixo + valor por ponto (dois campos)

    /**
     * Listar regras com filtros
     */
    public function listar($filtros = [])
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            SELECT 
                r.ID_REGRA,
                r.ID_EMPR,
                r.ID_FUNCIONARIO,
                f.COD_FUNC,
                f.NOME AS NOME_FUNCIONARIO,
                r.ID_CENTRO_TRAB,
                c.COD_CENTRO,
                c.DESCRICAO AS NOME_CENTRO,
                r.DESCRICAO,
                r.TIPO_COMISSAO,
                r.VALOR_COMISSAO,
                TO_CHAR(r.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI,
                TO_CHAR(r.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM,
                r.PRIORIDADE,
                r.ATIVO,
                TO_CHAR(r.DT_CADASTRO, 'DD/MM/YYYY HH24:MI') AS DT_CADASTRO,
                TO_CHAR(r.DT_ALTERACAO, 'DD/MM/YYYY HH24:MI') AS DT_ATUALIZACAO
            FROM FOCCO3I.TGAZIN_REGRA_FUNC r
            INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = r.ID_FUNCIONARIO
            LEFT JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = r.ID_CENTRO_TRAB
            WHERE 1=1
        ";

        $params = [];

        if (!empty($filtros['id_empr'])) {
            $sql .= " AND r.ID_EMPR = :id_empr";
            $params[':id_empr'] = $filtros['id_empr'];
        }

        if (!empty($filtros['id_funcionario'])) {
            $sql .= " AND r.ID_FUNCIONARIO = :id_funcionario";
            $params[':id_funcionario'] = $filtros['id_funcionario'];
        }

        if (!empty($filtros['id_centro_trab'])) {
            $sql .= " AND r.ID_CENTRO_TRAB = :id_centro_trab";
            $params[':id_centro_trab'] = $filtros['id_centro_trab'];
        }

        if (isset($filtros['status']) && $filtros['status'] !== '') {
            $sql .= " AND r.ATIVO = :status";
            $params[':status'] = $filtros['status'];
        } else {
            $sql .= " AND r.ATIVO = 'S'";
        }

        $sql .= " ORDER BY f.NOME, r.PRIORIDADE DESC, r.DT_VIGENCIA_INI DESC";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar regra por ID
     */
    public function buscarPorId($id)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            SELECT 
                r.*,
                f.COD_FUNC,
                f.NOME AS NOME_FUNCIONARIO,
                c.COD_CENTRO,
                c.DESCRICAO AS NOME_CENTRO,
                TO_CHAR(r.DT_VIGENCIA_INI, 'YYYY-MM-DD') AS DT_VIGENCIA_INI_FMT,
                TO_CHAR(r.DT_VIGENCIA_FIM, 'YYYY-MM-DD') AS DT_VIGENCIA_FIM_FMT
            FROM FOCCO3I.TGAZIN_REGRA_FUNC r
            INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = r.ID_FUNCIONARIO
            LEFT JOIN FOCCO3I.TCENTROS_TRAB c ON c.ID = r.ID_CENTRO_TRAB
            WHERE r.ID_REGRA = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Buscar regra ativa para funcionário em uma data específica
     * @param int $idFuncionario
     * @param int $idCentroTrab (opcional)
     * @param string $data (opcional, formato YYYY-MM-DD)
     * @param int $emprId (opcional)
     * @return array|null
     */
    public function buscarRegraAtiva($idFuncionario, $idCentroTrab = null, $data = null, $emprId = null)
    {
        $pdo = Database::getInstance('focco');
        
        if (!$data) {
            $data = date('Y-m-d');
        }

        // Se informado centro de trabalho, buscar regra específica primeiro
        if ($idCentroTrab) {
            $sqlCentro = "
                SELECT 
                    r.ID_REGRA,
                    r.*,
                    f.COD_FUNC,
                    f.NOME AS NOME_FUNCIONARIO
                FROM FOCCO3I.TGAZIN_REGRA_FUNC r
                INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = r.ID_FUNCIONARIO
                WHERE r.ID_FUNCIONARIO = :id_funcionario
                  AND r.ATIVO = 'S'
                  AND TO_DATE(:data, 'YYYY-MM-DD') >= r.DT_VIGENCIA_INI
                  AND (r.DT_VIGENCIA_FIM IS NULL OR TO_DATE(:data2, 'YYYY-MM-DD') <= r.DT_VIGENCIA_FIM)
                  AND r.ID_CENTRO_TRAB = :id_centro_trab";
            
            if ($emprId) {
                $sqlCentro .= " AND r.ID_EMPR = :id_empr";
            }
            
            $sqlCentro .= " ORDER BY r.PRIORIDADE DESC";
            
            $stmt = $pdo->prepare($sqlCentro);
            $stmt->bindValue(':id_funcionario', $idFuncionario, PDO::PARAM_INT);
            $stmt->bindValue(':data', $data);
            $stmt->bindValue(':data2', $data);
            $stmt->bindValue(':id_centro_trab', $idCentroTrab, PDO::PARAM_INT);
            if ($emprId) {
                $stmt->bindValue(':id_empr', $emprId, PDO::PARAM_INT);
            }
            $stmt->execute();
            
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($resultado) {
                return $resultado;
            }
        }

        // Buscar regra geral (sem centro)
        $sql = "
            SELECT 
                r.ID_REGRA,
                r.*,
                f.COD_FUNC,
                f.NOME AS NOME_FUNCIONARIO
            FROM FOCCO3I.TGAZIN_REGRA_FUNC r
            INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = r.ID_FUNCIONARIO
            WHERE r.ID_FUNCIONARIO = :id_funcionario
              AND r.ATIVO = 'S'
              AND TO_DATE(:data, 'YYYY-MM-DD') >= r.DT_VIGENCIA_INI
              AND (r.DT_VIGENCIA_FIM IS NULL OR TO_DATE(:data2, 'YYYY-MM-DD') <= r.DT_VIGENCIA_FIM)
              AND r.ID_CENTRO_TRAB IS NULL";
        
        if ($emprId) {
            $sql .= " AND r.ID_EMPR = :id_empr";
        }
        
        $sql .= " ORDER BY r.PRIORIDADE DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id_funcionario', $idFuncionario, PDO::PARAM_INT);
        $stmt->bindValue(':data', $data);
        $stmt->bindValue(':data2', $data);
        if ($emprId) {
            $stmt->bindValue(':id_empr', $emprId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Inserir nova regra
     */
    public function inserir($dados)
    {
        $pdo = Database::getInstance('focco');
        
        // Obter próximo ID - primeiro tenta sequência, depois MAX+1
        $novoId = null;
        
        try {
            // Tenta usar a sequência
            $seqStmt = $pdo->query("SELECT FOCCO3I.SEQ_TGAZIN_REGRA_FUNC.NEXTVAL AS ID FROM DUAL");
            $seqResult = $seqStmt->fetch(PDO::FETCH_ASSOC);
            $novoId = $seqResult['ID'] ?? null;
        } catch (\Exception $e) {
            // Sequência não existe, usar MAX + 1
            $novoId = null;
        }
        
        if (!$novoId) {
            // Se não houver sequência, usar MAX + 1
            $maxStmt = $pdo->query("SELECT NVL(MAX(ID_REGRA), 0) + 1 AS ID FROM FOCCO3I.TGAZIN_REGRA_FUNC");
            $maxResult = $maxStmt->fetch(PDO::FETCH_ASSOC);
            $novoId = $maxResult['ID'] ?? 1;
        }
        
        // Tratar data de vigência fim vazia
        $dtVigenciaFim = $dados['dt_vigencia_fim'] ?? null;
        if ($dtVigenciaFim === '' || $dtVigenciaFim === '31/12/2009') {
            $dtVigenciaFim = null;
        }
        
        $sql = "
            INSERT INTO FOCCO3I.TGAZIN_REGRA_FUNC (
                ID_REGRA, ID_EMPR, ID_FUNCIONARIO, ID_CENTRO_TRAB, DESCRICAO,
                TIPO_COMISSAO, VALOR_COMISSAO, VALOR_FIXO, DT_VIGENCIA_INI, DT_VIGENCIA_FIM,
                PRIORIDADE, ATIVO, DT_CADASTRO, ID_USUARIO_CAD
            ) VALUES (
                :id, :id_empr, :id_funcionario, :id_centro_trab, :descricao,
                :tipo_comissao, :valor_comissao, :valor_fixo, TO_DATE(:dt_vigencia_ini, 'YYYY-MM-DD'), 
                CASE WHEN :dt_vigencia_fim IS NOT NULL THEN TO_DATE(:dt_vigencia_fim2, 'YYYY-MM-DD') ELSE NULL END,
                :prioridade, 'S', SYSDATE, :id_usuario
            )
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $novoId, PDO::PARAM_INT);
        $stmt->bindValue(':id_empr', $dados['id_empr'], PDO::PARAM_INT);
        $stmt->bindValue(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmt->bindValue(':id_centro_trab', $dados['id_centro_trab'] ?: null);
        $stmt->bindValue(':descricao', $dados['descricao'] ?: null);
        $stmt->bindValue(':tipo_comissao', $dados['tipo_comissao']);
        $stmt->bindValue(':valor_comissao', $dados['valor_comissao'] ?? 0);
        $stmt->bindValue(':valor_fixo', $dados['valor_fixo'] ?: null);
        $stmt->bindValue(':dt_vigencia_ini', $dados['dt_vigencia_ini']);
        $stmt->bindValue(':dt_vigencia_fim', $dtVigenciaFim);
        $stmt->bindValue(':dt_vigencia_fim2', $dtVigenciaFim);
        $stmt->bindValue(':prioridade', $dados['prioridade'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $dados['id_usuario'] ?? null);
        
        if ($stmt->execute()) {
            return $novoId;
        }
        
        throw new \Exception('Falha ao inserir regra no banco de dados');
    }

    /**
     * Atualizar regra
     */
    public function atualizar($id, $dados)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            UPDATE FOCCO3I.TGAZIN_REGRA_FUNC SET
                ID_FUNCIONARIO = :id_funcionario,
                ID_CENTRO_TRAB = :id_centro_trab,
                DESCRICAO = :descricao,
                TIPO_COMISSAO = :tipo_comissao,
                VALOR_COMISSAO = :valor_comissao,
                VALOR_FIXO = :valor_fixo,
                DT_VIGENCIA_INI = TO_DATE(:dt_vigencia_ini, 'YYYY-MM-DD'),
                DT_VIGENCIA_FIM = TO_DATE(:dt_vigencia_fim, 'YYYY-MM-DD'),
                PRIORIDADE = :prioridade,
                DT_ALTERACAO = SYSDATE,
                ID_USUARIO_ALT = :id_usuario
            WHERE ID_REGRA = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':id_funcionario', $dados['id_funcionario'], PDO::PARAM_INT);
        $stmt->bindValue(':id_centro_trab', $dados['id_centro_trab'] ?: null);
        $stmt->bindValue(':descricao', $dados['descricao'] ?: null);
        $stmt->bindValue(':tipo_comissao', $dados['tipo_comissao']);
        $stmt->bindValue(':valor_comissao', $dados['valor_comissao'] ?? 0);
        $stmt->bindValue(':valor_fixo', $dados['valor_fixo'] ?: null);
        $stmt->bindValue(':dt_vigencia_ini', $dados['dt_vigencia_ini']);
        $stmt->bindValue(':dt_vigencia_fim', $dados['dt_vigencia_fim'] ?: null);
        $stmt->bindValue(':prioridade', $dados['prioridade'] ?? 1, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $dados['id_usuario'] ?? null);
        
        return $stmt->execute();
    }

    /**
     * Inativar regra (soft delete)
     */
    public function inativar($id, $idUsuario = null)
    {
        $pdo = Database::getInstance('focco');
        
        $sql = "
            UPDATE FOCCO3I.TGAZIN_REGRA_FUNC SET
                ATIVO = 'N',
                DT_ALTERACAO = SYSDATE,
                ID_USUARIO_ALT = :id_usuario
            WHERE ID_REGRA = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':id_usuario', $idUsuario);
        
        return $stmt->execute();
    }

    /**
     * Calcular comissão com base nos pontos e na regra específica
     * @param float $pontos Total de pontos do funcionário
     * @param array $regra Regra específica (deve conter TIPO_COMISSAO e VALOR_COMISSAO)
     * @param float $valorFixoAdicional Valor fixo adicional para tipo misto (opcional)
     * @return float Valor da comissão calculada
     */
    public function calcularComissao($pontos, $regra, $valorFixoAdicional = null)
    {
        if (!$regra || !isset($regra['TIPO_COMISSAO']) || !isset($regra['VALOR_COMISSAO'])) {
            return 0;
        }

        $valor = floatval($regra['VALOR_COMISSAO']);
        $tipo = $regra['TIPO_COMISSAO'];
        $valorFixo = floatval($regra['VALOR_FIXO'] ?? $valorFixoAdicional ?? 0);

        switch ($tipo) {
            case self::TIPO_VALOR_FIXO:
                // Valor fixo por ponto/UP (multiplica pela quantidade de pontos)
                return $pontos * $valor;
            
            case self::TIPO_PERCENTUAL:
                // Percentual sobre os pontos (valor é percentual)
                return $pontos * ($valor / 100);
            
            case self::TIPO_FIXO_TOTAL:
                // Valor fixo total - NÃO depende da quantidade produzida
                return $valor;
            
            case self::TIPO_MISTO:
                // Valor fixo + valor por ponto
                // VALOR_COMISSAO = valor por ponto, VALOR_FIXO = valor fixo base
                return $valorFixo + ($pontos * $valor);
            
            default:
                // Tipo desconhecido, usar valor fixo por ponto
                return $pontos * $valor;
        }
    }

    /**
     * MÉTODO OTIMIZADO - Buscar regras ativas de MÚLTIPLOS funcionários em uma única query
     * Evita N queries separadas
     * 
     * @param array $funcIds Array com IDs dos funcionários
     * @param int|null $centroTrabId (opcional)
     * @param string|null $data Data de referência (YYYY-MM-DD)
     * @param int|null $emprId (opcional)
     * @return array Indexado por ID do funcionário => regra ativa ou null
     */
    public function buscarRegraAtivaBatch(array $funcIds, ?int $centroTrabId = null, ?string $data = null, ?int $emprId = null): array
    {
        if (empty($funcIds)) {
            return [];
        }

        $pdo = Database::getInstance('focco');
        
        if (!$data) {
            $data = date('Y-m-d');
        }

        $placeholders = implode(',', array_fill(0, count($funcIds), '?'));

        // Buscar todas as regras ativas dos funcionários, ordenando para pegar a mais específica
        $sql = "SELECT 
                    r.ID_REGRA,
                    r.ID_FUNCIONARIO,
                    r.ID_CENTRO_TRAB,
                    r.DESCRICAO,
                    r.TIPO_COMISSAO,
                    r.VALOR_COMISSAO,
                    r.VALOR_FIXO,
                    r.PRIORIDADE,
                    f.COD_FUNC,
                    f.NOME AS NOME_FUNCIONARIO,
                    ROW_NUMBER() OVER (
                        PARTITION BY r.ID_FUNCIONARIO 
                        ORDER BY 
                            CASE WHEN r.ID_CENTRO_TRAB = ? THEN 1 ELSE 2 END,
                            r.PRIORIDADE DESC,
                            r.DT_VIGENCIA_INI DESC
                    ) AS RN
                FROM FOCCO3I.TGAZIN_REGRA_FUNC r
                INNER JOIN FOCCO3I.TFUNCIONARIOS f ON f.ID = r.ID_FUNCIONARIO
                WHERE r.ID_FUNCIONARIO IN ($placeholders)
                  AND r.ATIVO = 'S'
                  AND TO_DATE(?, 'YYYY-MM-DD') >= r.DT_VIGENCIA_INI
                  AND (r.DT_VIGENCIA_FIM IS NULL OR TO_DATE(?, 'YYYY-MM-DD') <= r.DT_VIGENCIA_FIM)";
        
        if ($emprId) {
            $sql .= " AND r.ID_EMPR = ?";
        }

        // Aplicar ROW_NUMBER para pegar apenas a regra mais prioritária por funcionário
        $sqlFinal = "SELECT * FROM ($sql) WHERE RN = 1";

        $stmt = $pdo->prepare($sqlFinal);
        
        $i = 1;
        // Centro de trabalho para ordenação (ou NULL se não informado)
        $stmt->bindValue($i++, $centroTrabId ?? 0, PDO::PARAM_INT);
        
        // IDs dos funcionários
        foreach ($funcIds as $funcId) {
            $stmt->bindValue($i++, $funcId, PDO::PARAM_INT);
        }
        
        // Datas
        $stmt->bindValue($i++, $data, PDO::PARAM_STR);
        $stmt->bindValue($i++, $data, PDO::PARAM_STR);
        
        if ($emprId) {
            $stmt->bindValue($i++, $emprId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Indexar por funcionário
        $regrasPorFunc = [];
        foreach ($funcIds as $funcId) {
            $regrasPorFunc[$funcId] = null;
        }
        
        foreach ($resultados as $regra) {
            $funcId = $regra['ID_FUNCIONARIO'];
            $regrasPorFunc[$funcId] = $regra;
        }

        return $regrasPorFunc;
    }
}
