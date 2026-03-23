<!-- Header - Estilo Precificação -->
<header class="ds-header">
    <div class="ds-header-nav">
        <?php if(!empty($empresa)): ?>
        <div class="ds-filial-label">
            <i class="bi bi-building"></i> <?= $empresa['codigo'] ?> - <?= $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?>
        </div>
        <?php endif; ?>
        <div class="ds-header-center">
        <?php if(isset($pageTitle) && $pageTitle): ?>
        <span class="ds-page-title"><?= htmlspecialchars($pageTitle) ?></span>
        <?php endif; ?>
        <span class="ds-header-sep">|</span>
        <?php 
        $acessoComissao = $is_admin || in_array('comissao', $rotas_permitidas) || in_array('*', $rotas_permitidas);
        $acessoCd = $is_admin || in_array('cd', $rotas_permitidas) || in_array('*', $rotas_permitidas);
        ?>

        <?php if($acessoComissao): ?>
        <a href="<?= $base ?>comissao-dashboard" class="ds-hdr-btn ds-hdr-btn-primary <?= (isset($pageActive) && $pageActive === 'comissao-dashboard') ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i> Dashboard
        </a>
        <a href="<?= $base ?>comissao-cadastro" class="ds-hdr-btn ds-hdr-btn-success <?= (isset($pageActive) && $pageActive === 'comissao-cadastro') ? 'active' : '' ?>">
            <i class="bi bi-gear-fill"></i> Cadastros
        </a>
        <a href="<?= $base ?>comissao-relatorio" class="ds-hdr-btn ds-hdr-btn-warning <?= (isset($pageActive) && $pageActive === 'comissao-relatorio') ? 'active' : '' ?>">
            <i class="bi bi-clipboard-data"></i> Relatórios
        </a>
        <?php endif; ?>

        <?php if($acessoCd): ?>
        <a href="<?= $base ?>cd-dashboard" class="ds-hdr-btn ds-hdr-btn-dark <?= (isset($pageActive) && $pageActive === 'dashboard') ? 'active' : '' ?>">
            <i class="bi bi-truck"></i> Dashboard CD
        </a>
        <a href="<?= $base ?>cd-calendario" class="ds-hdr-btn ds-hdr-btn-dark <?= (isset($pageActive) && $pageActive === 'calendario') ? 'active' : '' ?>">
            <i class="bi bi-calendar3"></i> Agendamento
        </a>
        <?php endif; ?>

        <?php if($is_admin): ?>
        <a href="<?= $base ?>permissao" class="ds-hdr-btn ds-hdr-btn-dark <?= (isset($pageActive) && $pageActive === 'permissao') ? 'active' : '' ?>">
            <i class="bi bi-shield-lock"></i> Permissões
        </a>
        <?php endif; ?>

        <?php if(isset($showRefreshBtn) && $showRefreshBtn): ?>
        <button id="btnAtualizar" class="ds-hdr-btn ds-hdr-btn-primary" onclick="carregarDashboard()">
            <i class="bi bi-arrow-clockwise"></i> Atualizar
        </button>
        <?php endif; ?>

        </div>
    </div>

    <a href="<?= $base ?>logout" class="ds-hdr-btn ds-hdr-btn-outline ds-btn-sair">
        <i class="bi bi-box-arrow-right"></i> Sair
    </a>
</header>

<style>
    /* =============================================
       Header - Idêntico ao Precificação
       ============================================= */
    .ds-header {
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: auto;
        padding: 16px 40px;
        position: sticky;
        top: 0;
        z-index: 1000;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    }

    .ds-page-title {
        font-family: 'Inter', sans-serif;
        font-size: 2rem;
        font-weight: 700;
        color: #4361ee;
        margin: 0;
        line-height: 1;
        white-space: nowrap;
    }

    .ds-header-sep {
        color: #d1d5db;
        font-size: 1.1rem;
        font-weight: 300;
    }

    /* Bloco de navegação (filial + botões) */
    .ds-header-nav {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
        flex: 1;
    }

    .ds-filial-label {
        font-family: 'Inter', sans-serif;
        font-size: 1.1rem;
        font-weight: 700;
        color: #374151;
        white-space: nowrap;
        width: 100%;
        text-align: center;
    }

    .ds-filial-label i {
        font-size: 0.85rem;
    }

    .ds-btn-sair {
        margin-left: auto;
        flex-shrink: 0;
    }

    /* Botões centralizados */
    .ds-header-center {
        display: flex;
        align-items: flex-end;
        gap: 12px;
        flex-wrap: nowrap;
    }

    .ds-empresa-info {
        font-family: 'Inter', sans-serif;
        font-size: 0.8rem;
        font-weight: 500;
        color: #6b7280;
        white-space: nowrap;
    }

    /* =============================================
       Botões do Header - Estilo Precificação
       ============================================= */
    .ds-hdr-btn {
        font-family: 'Inter', sans-serif;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 10px 20px;
        font-size: 0.85rem;
        font-weight: 600;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        text-decoration: none !important;
        transition: all 0.2s ease;
        white-space: nowrap;
        letter-spacing: 0.01em;
    }

    .ds-hdr-btn:hover {
        transform: translateY(-2px);
        text-decoration: none !important;
    }

    .ds-hdr-btn:active {
        transform: translateY(0);
    }

    .ds-hdr-btn i {
        font-size: 1.1em;
    }

    /* Azul - Dashboard */
    .ds-hdr-btn-primary {
        background: #4361ee;
        color: #fff !important;
    }
    .ds-hdr-btn-primary:hover {
        background: #3a56d4;
        color: #fff !important;
        box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
    }

    /* Verde - Cadastros */
    .ds-hdr-btn-success {
        background: #2dc653;
        color: #fff !important;
    }
    .ds-hdr-btn-success:hover {
        background: #25a847;
        color: #fff !important;
        box-shadow: 0 6px 20px rgba(45, 198, 83, 0.4);
    }

    /* Laranja - Relatórios */
    .ds-hdr-btn-warning {
        background: #e85d04;
        color: #fff !important;
    }
    .ds-hdr-btn-warning:hover {
        background: #cc5003;
        color: #fff !important;
        box-shadow: 0 6px 20px rgba(232, 93, 4, 0.4);
    }

    /* Escuro */
    .ds-hdr-btn-dark {
        background: #2b2d42;
        color: #fff !important;
    }
    .ds-hdr-btn-dark:hover {
        background: #3d3f56;
        color: #fff !important;
        box-shadow: 0 6px 20px rgba(43, 45, 66, 0.4);
    }

    /* Outline - Sair */
    .ds-hdr-btn-outline {
        background: #fff;
        color: #1a1d2e !important;
        border: 2px solid #e8ecf1;
    }
    .ds-hdr-btn-outline:hover {
        background: #f5f7fa;
        color: #1a1d2e !important;
        border-color: #ccc;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    /* Responsividade */
    @media (max-width: 1400px) {
        .ds-header {
            padding: 20px 24px;
            gap: 20px;
        }
        .ds-hdr-btn {
            padding: 10px 20px;
            font-size: 0.85rem;
        }
        .ds-page-title {
            font-size: 1.6rem;
        }
    }

    @media (max-width: 1200px) {
        .ds-header {
            flex-wrap: wrap;
        }
        .ds-header-center {
            flex-wrap: wrap;
            gap: 8px;
        }
        .ds-hdr-btn {
            padding: 8px 16px;
            font-size: 0.8rem;
        }
    }

    @media (max-width: 768px) {
        .ds-header {
            flex-direction: column;
            align-items: stretch;
            padding: 16px;
        }
        .ds-header-center {
            justify-content: center;
        }
        .ds-header-right {
            text-align: center;
            margin-left: 0;
        }
        .ds-page-title {
            text-align: center;
            font-size: 1.4rem;
        }
        .ds-page-subtitle {
            text-align: center;
        }
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
