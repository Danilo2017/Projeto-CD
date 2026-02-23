<!-- Header -->
<header class="cd-header">
    <div class="cd-logo">
        <img src="https://systemcolchoes.blob.core.windows.net/site-gazin-colchoes/prod/Logo_Gazin_6a2b1ee6aa.png" alt="Gazin Colchões" onerror="this.style.display='none'">
    </div>
    <div style="flex: 1; text-align: center;">
        <h1 class="cd-title"><?= isset($pageTitle) ? strtoupper($pageTitle) : 'SISTEMA CD' ?></h1>
        <?php if(isset($showLastUpdate) && $showLastUpdate): ?>
        <div style="font-size: 10px; color: rgba(255,255,255,0.8); margin-top: 2px;">
            Última atualização: <span id="ultima-atualizacao">--:--:--</span>
        </div>
        <?php endif; ?>
    </div>
    <div class="cd-logout">
        <?php if(isset($_SESSION['empresa']) && !empty($_SESSION['empresa'])): ?>
        <span class="empresa-badge" title="Empresa selecionada">
            <i class="bi bi-building"></i>
            <?= $_SESSION['empresa']['codigo'] ?> - <?= $_SESSION['empresa']['nome_fantasia'] ?? $_SESSION['empresa']['razao_social'] ?>
        </span>
        <?php endif; ?>
        <a href="<?= $base ?>logout" class="btn-logout">
            <i class="bi bi-box-arrow-right"></i> Sair
        </a>
    </div>
</header>

<!-- Menu de navegação -->
<div class="cd-info-bar">
    <div class="cd-info-item">
        <?php 
        // Verificar permissões do usuário
        $acessoComissao = $_SESSION['user']['acesso_comissao'] ?? 'N';
        $acessoCd = $_SESSION['user']['acesso_cd'] ?? 'N';
        $isAdmin = $_SESSION['user']['admin'] ?? 'N';
        $temPermissao = $_SESSION['user']['tem_permissao'] ?? false;
        ?>
        
        <?php if($acessoComissao === 'S'): ?>
        <!-- Menu Comissão -->
        <a href="<?= $base ?>comissao-cadastro" class="cd-btn-menu <?= (isset($pageActive) && $pageActive === 'comissao-cadastro') ? 'active' : '' ?>">⚙️ Cadastros</a>
        <a href="<?= $base ?>comissao-relatorio" class="cd-btn-menu <?= (isset($pageActive) && $pageActive === 'comissao-relatorio') ? 'active' : '' ?>">📋 Relatórios</a>
        <?php endif; ?>
        
        <?php if($acessoCd === 'S'): ?>
        <!-- Menu CD -->
        <a href="<?= $base ?>cd-dashboard" class="cd-btn-menu <?= (isset($pageActive) && $pageActive === 'dashboard') ? 'active' : '' ?>">🏠 Dashboard CD</a>
        <a href="<?= $base ?>cd-calendario" class="cd-btn-menu <?= (isset($pageActive) && $pageActive === 'calendario') ? 'active' : '' ?>">📅 Agendamento</a>
        <?php endif; ?>
        
        <?php if($isAdmin === 'S'): ?>
        <!-- Menu Admin -->
        <a href="<?= $base ?>permissao" class="cd-btn-menu <?= (isset($pageActive) && $pageActive === 'permissao') ? 'active' : '' ?>">🔐 Permissões</a>
        <?php endif; ?>
        
        <?php if(isset($showRefreshBtn) && $showRefreshBtn): ?>
        <button id="btnAtualizar" class="cd-btn-refresh" onclick="carregarDashboard()">🔄 Atualizar</button>
        <?php endif; ?>
    </div>
    <div class="cd-info-item">
        <span class="cd-info-value" id="dateTime">--/--/---- --:--:--</span>
    </div>
</div>

<style>
    /* Estilos do header */
    .cd-header {
        background: linear-gradient(135deg, #004080 0%, #0059b3 100%);
        color: white;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    .cd-logo {
        min-width: 150px;
    }

    .cd-logo img {
        height: 40px;
        filter: brightness(0) invert(1);
    }

    .cd-title {
        font-size: 20px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 0;
    }

    .cd-logout {
        min-width: 150px;
        display: flex;
        justify-content: flex-end;
        align-items: center;
    }

    .btn-logout {
        background: #d9534f;
        color: #fff;
        padding: 7px 22px;
        font-size: 1.08rem;
        border-radius: 22px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border: none;
        text-decoration: none;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: background 0.2s;
        white-space: nowrap;
    }

    .btn-logout:hover {
        background: #c9302c;
    }

    /* Badge da empresa selecionada */
    .empresa-badge {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        margin-right: 12px;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .empresa-badge i {
        font-size: 0.9rem;
    }

    /* Menu de navegação */
    .cd-info-bar {
        background: white;
        padding: 10px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .cd-info-item {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .cd-btn-menu {
        padding: 8px 16px;
        background: white;
        color: #004080;
        border: 2px solid #004080;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s;
        cursor: pointer;
    }

    .cd-btn-menu:hover,
    .cd-btn-menu.active {
        background: #004080;
        color: white;
    }

    .cd-btn-refresh {
        padding: 8px 16px;
        background: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .cd-btn-refresh:hover {
        background: #218838;
    }

    .cd-info-value {
        color: #004080;
        font-weight: 600;
        font-size: 14px;
    }
</style>

<script>
// Atualizar horário em tempo real
function atualizarHora() {
    var agora = new Date();
    var dia = String(agora.getDate()).padStart(2, '0');
    var mes = String(agora.getMonth() + 1).padStart(2, '0');
    var ano = agora.getFullYear();
    var hora = String(agora.getHours()).padStart(2, '0');
    var min = String(agora.getMinutes()).padStart(2, '0');
    var seg = String(agora.getSeconds()).padStart(2, '0');
    
    var elemDateTime = document.getElementById('dateTime');
    if (elemDateTime) {
        elemDateTime.textContent = dia + '/' + mes + '/' + ano + ' ' + hora + ':' + min + ':' + seg;
    }
}

atualizarHora();
setInterval(atualizarHora, 1000);
</script>
