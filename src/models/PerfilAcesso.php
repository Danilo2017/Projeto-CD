<?php

namespace src\models;

use core\Database;
use src\utils\GetSqlFocco;

/**
 * Model para gerenciar perfis de acesso (tabelas novas)
 * TGAZIN_PERFIL_ACESSO, TGAZIN_PERFIL_ROTA, TGAZIN_USUARIO_PERFIL
 */
class PerfilAcesso
{
    // ==================== PERFIS ====================

    public static function listarPerfis()
    {
        $result = Database::switchParams('focco', [], 'acesso.perfil.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listarPerfisAtivos()
    {
        $result = Database::switchParams('focco', [], 'acesso.perfil.listarAtivos', true);
        return $result['retorno'] ?? [];
    }

    // ==================== USUÁRIOS X PERFIS ====================

    public static function listarUsuarios($filtros = [])
    {
        $params = [
            'filtro_login'  => !empty($filtros['login'])
                ? "AND UPPER(UP.LOGIN_USUARIO) LIKE UPPER('%" . str_replace("'", "''", $filtros['login']) . "%')"
                : '--',
            'filtro_ativo'  => isset($filtros['ativo'])
                ? "AND UP.ATIVO = '" . ($filtros['ativo'] === 'S' ? 'S' : 'N') . "'"
                : '--',
            'filtro_perfil' => !empty($filtros['perfil_id'])
                ? "AND UP.PERFIL_ID = " . intval($filtros['perfil_id'])
                : '--',
        ];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function listarUsuariosAgrupados($filtros = [])
    {
        $dados = self::listarUsuarios($filtros);

        $usuarios = [];
        foreach ($dados as $row) {
            $login = $row['LOGIN_USUARIO'];

            if (!isset($usuarios[$login])) {
                $usuarios[$login] = [
                    'LOGIN_USUARIO' => $login,
                    'ATIVO'         => $row['ATIVO'],
                    'PERFIS'        => [],
                    'PERFIS_IDS'    => [],
                    'DT_CADASTRO'   => $row['DT_CADASTRO'],
                ];
            }

            $usuarios[$login]['PERFIS'][]     = $row['PERFIL_NOME'];
            $usuarios[$login]['PERFIS_IDS'][] = $row['PERFIL_ID'];
        }

        return array_values($usuarios);
    }

    public static function buscarPerfisUsuario($login)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.buscarPerfis', true);
        return $result['retorno'] ?? [];
    }

    public static function buscarPorId($id)
    {
        $params = ['id' => intval($id)];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.buscarPorId', true);
        return ($result['retorno'][0] ?? null) ?: null;
    }

    public static function adicionarPerfilUsuario($login, $perfilId)
    {
        $loginEsc  = str_replace("'", "''", $login);
        $perfilInt = intval($perfilId);

        // Verificar se já existe (ativo ou inativo)
        $checkParams = ['login' => "'" . $loginEsc . "'", 'perfil_id' => $perfilInt];
        $checkResult = Database::switchParams('focco', $checkParams, 'acesso.usuario.verificarPerfil', true);
        $registros   = $checkResult['retorno'] ?? [];

        if (!empty($registros)) {
            // Algum está ativo — retorna sem criar duplicata
            foreach ($registros as $reg) {
                if ($reg['ATIVO'] === 'S') {
                    return $reg['ID_USUARIO_PERFIL'];
                }
            }

            $primeiroId = intval($registros[0]['ID_USUARIO_PERFIL']);

            // Remover registros inativos duplicados (manter apenas o mais recente)
            if (count($registros) > 1) {
                $idsParaDeletar = [];
                for ($i = 1; $i < count($registros); $i++) {
                    $idsParaDeletar[] = intval($registros[$i]['ID_USUARIO_PERFIL']);
                }
                $pdo = Database::getInstance('focco');
                $sqlDelete = str_replace('{lista_ids}', implode(',', $idsParaDeletar), GetSqlFocco::getSql('acesso.usuario.removerPerfisDuplicados'));
                $pdo->exec($sqlDelete);
            }

            // Reativar o registro mais recente
            Database::switchParams('focco', ['id' => $primeiroId], 'acesso.usuario.reativarPerfil', true);
            return $primeiroId;
        }

        // Inserir novo vínculo
        Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'perfil_id' => $perfilInt], 'acesso.usuario.inserirPerfil', true);

        // Buscar ID inserido
        $idResult = Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'perfil_id' => $perfilInt], 'acesso.usuario.buscarIdPerfil', true);
        return intval($idResult['retorno'][0]['ID'] ?? 0);
    }

    public static function definirPerfisUsuario($login, array $perfisIds)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        Database::switchParams('focco', $params, 'acesso.usuario.inativarPerfis', true);

        foreach ($perfisIds as $perfilId) {
            if ($perfilId) {
                self::adicionarPerfilUsuario($login, $perfilId);
            }
        }

        return true;
    }

    public static function removerPerfilUsuario($id)
    {
        $result = Database::switchParams('focco', ['id' => intval($id)], 'acesso.usuario.removerPerfil', true);
        return empty($result['error']);
    }

    public static function isAdmin($login)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.isAdmin', true);
        return !empty($result['retorno']);
    }

    public static function temAcessoModulo($login, $modulo)
    {
        $params = [
            'login'  => "'" . str_replace("'", "''", $login) . "'",
            'modulo' => "'" . str_replace("'", "''", $modulo) . "'",
        ];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.temAcessoModulo', true);
        return !empty($result['retorno']);
    }

    // ==================== USUÁRIOS X FILIAIS ====================

    public static function listarEmpresas()
    {
        $result = Database::switchParams('focco', [], 'acesso.empresa.listar', true);
        return $result['retorno'] ?? [];
    }

    public static function buscarFiliaisUsuario($login)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        $result = Database::switchParams('focco', $params, 'acesso.usuario.buscarFiliais', true);
        return $result['retorno'] ?? [];
    }

    public static function adicionarFilialUsuario($login, $emprId)
    {
        $loginEsc = str_replace("'", "''", $login);
        $emprInt  = intval($emprId);

        // Verificar se já existe (ativo ou inativo)
        $checkParams = ['login' => "'" . $loginEsc . "'", 'empr_id' => $emprInt];
        $checkResult = Database::switchParams('focco', $checkParams, 'acesso.usuario.verificarFilial', true);
        $registros   = $checkResult['retorno'] ?? [];

        if (!empty($registros)) {
            foreach ($registros as $reg) {
                if ($reg['ATIVO'] === 'S') {
                    return $reg['ID_USUARIO_FILIAL'];
                }
            }

            $primeiroId = intval($registros[0]['ID_USUARIO_FILIAL']);

            // Remover registros inativos duplicados
            if (count($registros) > 1) {
                $idsParaDeletar = [];
                for ($i = 1; $i < count($registros); $i++) {
                    $idsParaDeletar[] = intval($registros[$i]['ID_USUARIO_FILIAL']);
                }
                $pdo = Database::getInstance('focco');
                $sqlDelete = str_replace('{lista_ids}', implode(',', $idsParaDeletar), GetSqlFocco::getSql('acesso.usuario.removerFiliaisDuplicadas'));
                $pdo->exec($sqlDelete);
            }

            // Reativar o registro mais recente
            Database::switchParams('focco', ['id' => $primeiroId], 'acesso.usuario.reativarFilial', true);
            return $primeiroId;
        }

        // Inserir nova filial
        Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'empr_id' => $emprInt], 'acesso.usuario.inserirFilial', true);

        // Buscar ID inserido
        $idResult = Database::switchParams('focco', ['login' => "'" . $loginEsc . "'", 'empr_id' => $emprInt], 'acesso.usuario.buscarIdFilial', true);
        return intval($idResult['retorno'][0]['ID'] ?? 0);
    }

    public static function definirFiliaisUsuario($login, array $filiaisIds)
    {
        $params = ['login' => "'" . str_replace("'", "''", $login) . "'"];
        Database::switchParams('focco', $params, 'acesso.usuario.inativarFiliais', true);

        foreach ($filiaisIds as $emprId) {
            if ($emprId) {
                self::adicionarFilialUsuario($login, $emprId);
            }
        }

        return true;
    }

    public static function temAcessoFilial($login, $emprId)
    {
        $filiais = self::buscarFiliaisUsuario($login);
        if (empty($filiais)) {
            return true;
        }

        foreach ($filiais as $filial) {
            if ($filial['EMPR_ID'] == $emprId) {
                return true;
            }
        }

        return false;
    }

    public static function getFiliaisPermitidas($login)
    {
        $filiais = self::buscarFiliaisUsuario($login);
        return array_column($filiais, 'EMPR_ID');
    }
}
