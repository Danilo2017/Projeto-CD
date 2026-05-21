<?php
$acessoComissao    = $is_admin || in_array('comissao',    $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoCd          = $is_admin || in_array('cd',          $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);

$pActive = $pageActive ?? '';
$activeGroup = match(true) {
    str_starts_with($pActive, 'comissao-') || $pActive === 'comissao'                              => 'comissao',
    str_starts_with($pActive, 'cd-') || in_array($pActive, ['dashboard', 'calendario'])            => 'cd',
    str_starts_with($pActive, 'faturamento-') || in_array($pActive, ['faturamento', 'meta-empresa']) => 'faturamento',
    $pActive === 'permissao'                                                                        => 'permissao',
    default => ''
};

$comissaoSub = match(true) {
    $pActive === 'comissao-dashboard'                                                   => 'dashboard',
    str_starts_with($pActive, 'comissao-relatorio') || $pActive === 'comissao-extrato-analitico' => 'relatorios',
    str_starts_with($pActive, 'comissao-')                                              => 'cadastros',
    default => ''
};
$cdSub = match(true) {
    in_array($pActive, ['cd-dashboard', 'dashboard'])      => 'dashboard',
    in_array($pActive, ['cd-calendario', 'calendario'])    => 'calendario',
    default => ''
};
$fatSub = match(true) {
    in_array($pActive, ['faturamento-dashboard', 'faturamento']) => 'dashboard',
    $pActive === 'meta-empresa'                                  => 'metas',
    $pActive === 'faturamento-programacao'                       => 'programacao',
    default => ''
};

$userName = $user_login ?? 'Usuário';
?>

<!-- ═══════════════════════════════════════
     FLYOUT PANEL (posicionado por JS)
═══════════════════════════════════════ -->
<div id="sbFlyout" class="sb-flyout" style="display:none;" aria-hidden="true">
    <div class="sb-flyout-title"></div>
    <div class="sb-flyout-body"></div>
</div>

<!-- ═══════════════════════════════════════
     SIDEBAR
═══════════════════════════════════════ -->
<nav class="app-sidebar" id="appSidebar">

    <div class="sidebar-brand">
        <button class="sidebar-toggle-btn" id="sidebarToggle" title="Expandir / recolher menu">
            <i class="bi bi-list"></i>
        </button>
        <img src="https://system.colchoesgazin.com.br/assets/media/logos/logo-gazin.png"
             alt="Gazin" class="sidebar-brand-logo">
    </div>

    <ul class="sidebar-menu">

        <?php if ($acessoComissao): ?>
        <li class="sidebar-group" id="grpComissao">
            <button class="sidebar-group-btn <?= $activeGroup === 'comissao' ? 'active open' : '' ?>">
                <i class="bi bi-award"></i>
                <span>Comissão</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'comissao' ? 'open' : '' ?>">
                <li>
                    <a href="<?= $base ?>comissao-dashboard"
                       class="sidebar-sublink <?= $comissaoSub === 'dashboard' ? 'active' : '' ?>">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>comissao-cadastro"
                       class="sidebar-sublink <?= $comissaoSub === 'cadastros' ? 'active' : '' ?>">
                        Cadastros
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>comissao-relatorio"
                       class="sidebar-sublink <?= $comissaoSub === 'relatorios' ? 'active' : '' ?>">
                        Relatórios
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoCd): ?>
        <li class="sidebar-group" id="grpCd">
            <button class="sidebar-group-btn <?= $activeGroup === 'cd' ? 'active open' : '' ?>">
                <i class="bi bi-truck"></i>
                <span>CD</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'cd' ? 'open' : '' ?>">
                <li>
                    <a href="<?= $base ?>cd-dashboard"
                       class="sidebar-sublink <?= $cdSub === 'dashboard' ? 'active' : '' ?>">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>cd-calendario"
                       class="sidebar-sublink <?= $cdSub === 'calendario' ? 'active' : '' ?>">
                        Agendamento
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoFaturamento): ?>
        <li class="sidebar-group" id="grpFaturamento">
            <button class="sidebar-group-btn <?= $activeGroup === 'faturamento' ? 'active open' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                <span>Faturamento</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'faturamento' ? 'open' : '' ?>">
                <li>
                    <a href="<?= $base ?>faturamento-dashboard"
                       class="sidebar-sublink <?= $fatSub === 'dashboard' ? 'active' : '' ?>">
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>meta-empresa"
                       class="sidebar-sublink <?= $fatSub === 'metas' ? 'active' : '' ?>">
                        Metas
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>faturamento-programacao"
                       class="sidebar-sublink <?= $fatSub === 'programacao' ? 'active' : '' ?>">
                        Programação
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($is_admin): ?>
        <li>
            <a href="<?= $base ?>permissao"
               class="sidebar-link <?= $activeGroup === 'permissao' ? 'active' : '' ?>"
               data-tooltip="Permissões">
                <i class="bi bi-shield-lock"></i>
                <span>Permissões</span>
            </a>
        </li>
        <?php endif; ?>

    </ul>

    <div class="sidebar-bottom">
        <a href="<?= $base ?>logout" class="sidebar-link sidebar-logout" data-tooltip="Sair do Sistema">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sair</span>
        </a>
    </div>

</nav>

<!-- ═══════════════════════════════════════
     TOP HEADER
═══════════════════════════════════════ -->
<header class="app-header" id="appHeader">

    <div class="app-header-left">
        <?php if (!empty($pageTitle)): ?>
        <span class="app-header-title"><?= htmlspecialchars($pageTitle) ?></span>
        <?php endif; ?>
        <?php if (!empty($empresa)): ?>
        <span class="app-header-empresa">
            <i class="bi bi-building"></i>
            <?= htmlspecialchars($empresa['codigo'] ?? '') ?> &mdash; <?= htmlspecialchars($empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? '') ?>
        </span>
        <?php endif; ?>
    </div>

    <div class="app-header-user">
        <div class="app-user-avatar"><i class="bi bi-person-fill"></i></div>
        <div class="app-user-info">
            <span class="app-user-name"><?= htmlspecialchars($userName) ?></span>
            <?php if (!empty($empresa)): ?>
            <span class="app-user-role"><?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?></span>
            <?php endif; ?>
        </div>
    </div>

</header>

<!-- ═══════════════════════════════════════
     ESTILOS
═══════════════════════════════════════ -->
<style>
:root {
    --sb-w:      64px;
    --sb-w-exp:  240px;
    --hdr-h:     60px;
    --hdr-bg:    #2563eb;
    --sb-accent: #2563eb;
}

/* ─── Sidebar ─────────────────────────────── */
.app-sidebar {
    position: fixed;
    top: 0; left: 0;
    width: var(--sb-w);
    height: 100vh;
    background: #fff;
    border-right: 1px solid #e5e7eb;
    z-index: 1010;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    transition: width 0.28s ease;
    box-shadow: 2px 0 10px rgba(0,0,0,0.06);
}
.app-sidebar.expanded { width: var(--sb-w-exp); }

/* brand / toggle */
.sidebar-brand {
    height: var(--hdr-h);
    display: flex;
    align-items: center;
    border-bottom: 1px solid #f0f0f0;
    flex-shrink: 0;
}
.sidebar-toggle-btn {
    background: none; border: none; cursor: pointer;
    width: var(--sb-w); min-width: var(--sb-w);
    height: var(--hdr-h);
    display: flex; align-items: center; justify-content: center;
    color: #6b7280; font-size: 1.35rem;
    flex-shrink: 0;
    transition: color 0.2s, background 0.2s;
}
.sidebar-toggle-btn:hover { color: var(--sb-accent); background: #f3f4f6; }
.sidebar-brand-logo {
    height: 42px; width: auto; object-fit: contain;
    max-width: 160px;
    opacity: 0; transition: opacity 0.2s;
    pointer-events: none; flex-shrink: 0;
}
.app-sidebar.expanded .sidebar-brand-logo { opacity: 1; }

/* menu */
.sidebar-menu {
    list-style: none; padding: 8px 0; margin: 0;
    flex: 1; overflow-y: auto; overflow-x: hidden;
}

/* ─── Standalone links (Permissões, Sair) ─── */
.sidebar-link {
    display: flex; align-items: center;
    margin: 2px 8px; padding: 0; height: 44px;
    border-radius: 10px;
    color: #374151; text-decoration: none;
    white-space: nowrap; overflow: hidden;
    transition: background 0.18s, color 0.18s;
    position: relative;
}
.sidebar-link i {
    font-size: 1.15rem;
    width: calc(var(--sb-w) - 16px); min-width: calc(var(--sb-w) - 16px);
    text-align: center; flex-shrink: 0;
    color: #6b7280; transition: color 0.18s;
}
.sidebar-link span {
    font-size: 0.875rem; font-weight: 500;
    padding-right: 12px; white-space: nowrap;
    opacity: 0; transition: opacity 0.18s; pointer-events: none;
}
.app-sidebar.expanded .sidebar-link span { opacity: 1; }
.sidebar-link:hover { background: #f3f4f6; color: var(--sb-accent); }
.sidebar-link:hover i { color: var(--sb-accent); }
.sidebar-link.active { background: #eff6ff; color: var(--sb-accent); }
.sidebar-link.active i { color: var(--sb-accent); }

/* tooltip nos links standalone */
.app-sidebar:not(.expanded) .sidebar-link::after {
    content: attr(data-tooltip);
    position: absolute;
    left: calc(var(--sb-w) + 6px);
    top: 50%; transform: translateY(-50%);
    background: #1f2937; color: #fff;
    padding: 5px 10px; border-radius: 6px;
    font-size: 0.78rem; white-space: nowrap;
    pointer-events: none; opacity: 0;
    transition: opacity 0.15s; z-index: 1020;
}
.app-sidebar:not(.expanded) .sidebar-link:hover::after { opacity: 1; }

/* ─── Grupo expansível ─────────────────────── */
.sidebar-group { margin: 2px 0; }

.sidebar-group-btn {
    display: flex; align-items: center;
    width: calc(100% - 16px); /* 8px margin cada lado */
    margin: 2px 8px;
    height: 44px;
    border-radius: 10px;
    border: none; background: none; cursor: pointer;
    color: #374151; white-space: nowrap; overflow: hidden;
    transition: background 0.18s, color 0.18s;
    position: relative;
    padding: 0;
}
.sidebar-group-btn i:first-child {
    font-size: 1.15rem;
    width: calc(var(--sb-w) - 16px); min-width: calc(var(--sb-w) - 16px);
    text-align: center; flex-shrink: 0;
    color: #6b7280; transition: color 0.18s;
}
.sidebar-group-btn > span {
    font-size: 0.875rem; font-weight: 500;
    white-space: nowrap; flex: 1;
    opacity: 0; transition: opacity 0.18s; pointer-events: none;
    text-align: left;
}
.app-sidebar.expanded .sidebar-group-btn > span { opacity: 1; }
.sidebar-group-btn:hover { background: #f3f4f6; color: var(--sb-accent); }
.sidebar-group-btn:hover i:first-child { color: var(--sb-accent); }
.sidebar-group-btn.active { background: #eff6ff; color: var(--sb-accent); }
.sidebar-group-btn.active i:first-child { color: var(--sb-accent); }

/* Chevron */
.group-chevron {
    font-size: 0.75rem; flex-shrink: 0;
    padding-right: 10px; margin-left: auto;
    opacity: 0;
    transition: transform 0.22s ease, opacity 0.18s;
}
.app-sidebar.expanded .group-chevron { opacity: 1; }
.sidebar-group-btn.open .group-chevron { transform: rotate(180deg); }

/* ─── Submenu ──────────────────────────────── */
.sidebar-submenu {
    list-style: none; padding: 0; margin: 0;
    max-height: 0; overflow: hidden;
    transition: max-height 0.25s ease;
}
.sidebar-submenu.open { max-height: 400px; }
/* No collapsed mode, submenus ficam ocultos (flyout substitui) */
.app-sidebar:not(.expanded) .sidebar-submenu { max-height: 0 !important; }

.sidebar-sublink {
    display: block;
    padding: 8px 12px 8px 54px;
    margin: 1px 8px;
    border-radius: 8px;
    color: #6b7280; text-decoration: none;
    font-size: 0.85rem; font-weight: 400;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s;
    position: relative;
}
.sidebar-sublink::before {
    content: '';
    position: absolute;
    left: 34px; top: 50%; transform: translateY(-50%);
    width: 6px; height: 6px; border-radius: 50%;
    background: #d1d5db;
    transition: background 0.15s;
}
.sidebar-sublink:hover { background: #f3f4f6; color: var(--sb-accent); }
.sidebar-sublink:hover::before { background: var(--sb-accent); }
.sidebar-sublink.active { color: var(--sb-accent); font-weight: 600; }
.sidebar-sublink.active::before { background: var(--sb-accent); }

/* ─── Rodapé / Sair ─────────────────────────── */
.sidebar-bottom {
    padding: 8px 0;
    border-top: 1px solid #f0f0f0;
    flex-shrink: 0;
}
.sidebar-logout i  { color: #dc2626 !important; }
.sidebar-logout:hover { background: #fef2f2 !important; color: #dc2626 !important; }

/* ─── Header ──────────────────────────────── */
.app-header {
    position: fixed;
    top: 0;
    left: var(--sb-w, 64px);
    right: 0;
    height: var(--hdr-h, 60px);
    background-color: #2563eb;
    z-index: 1000;
    display: flex; align-items: center;
    padding: 0 24px; gap: 16px;
    transition: left 0.28s ease;
    box-shadow: 0 2px 10px rgba(37,99,235,0.25);
}
body.sidebar-expanded .app-header {
    left: var(--sb-w-exp, 240px);
}

.app-header-left {
    display: flex; align-items: center; gap: 16px; flex: 1; min-width: 0;
}
.app-header-title {
    font-size: 1.05rem; font-weight: 700; color: #fff;
    white-space: nowrap;
}
.app-header-empresa {
    font-size: 0.8rem; color: rgba(255,255,255,0.75);
    display: flex; align-items: center; gap: 5px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.app-header-empresa i { font-size: 0.85rem; }

.app-header-user {
    display: flex; align-items: center;
    gap: 10px; flex-shrink: 0; margin-left: auto;
}
.app-user-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: rgba(255,255,255,0.2);
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 1.1rem;
}
.app-user-info { display: flex; flex-direction: column; }
.app-user-name  { font-size: 0.875rem; font-weight: 600; color: #fff; white-space: nowrap; }
.app-user-role  { font-size: 0.72rem; color: rgba(255,255,255,0.7); white-space: nowrap; }

/* ─── Flyout panel (collapsed mode) ─────────── */
.sb-flyout {
    position: fixed;
    left: calc(var(--sb-w, 64px) + 4px);
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    box-shadow: 4px 8px 24px rgba(0,0,0,0.13);
    min-width: 168px;
    z-index: 1050;
    padding: 6px 0 4px;
}
.sb-flyout-title {
    font-size: 0.68rem; font-weight: 700;
    color: #9ca3af; text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 4px 14px 6px;
    border-bottom: 1px solid #f3f4f6;
    margin-bottom: 4px;
}
.sb-flyout-body a {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 16px;
    color: #374151; text-decoration: none;
    font-size: 0.875rem; font-weight: 400;
    transition: background 0.15s, color 0.15s;
    position: relative;
}
.sb-flyout-body a::before {
    content: '';
    width: 6px; height: 6px; border-radius: 50%;
    background: #d1d5db; flex-shrink: 0;
    transition: background 0.15s;
}
.sb-flyout-body a:hover { background: #f3f4f6; color: var(--sb-accent, #2563eb); }
.sb-flyout-body a:hover::before { background: var(--sb-accent, #2563eb); }
.sb-flyout-body a.active { color: var(--sb-accent, #2563eb); font-weight: 600; }
.sb-flyout-body a.active::before { background: var(--sb-accent, #2563eb); }

/* ─── Responsivo ──────────────────────────── */
@media (max-width: 768px) {
    .app-sidebar { left: -80px; box-shadow: none; }
    .app-sidebar.expanded {
        left: 0; width: 240px;
        box-shadow: 4px 0 20px rgba(0,0,0,0.15);
    }
    .app-header { left: 0 !important; }
}
</style>

<!-- ═══════════════════════════════════════
     SCRIPT
═══════════════════════════════════════ -->
<script>
(function () {
    var sidebar   = document.getElementById('appSidebar');
    var body      = document.body;
    var flyout    = document.getElementById('sbFlyout');
    var flyoutTimer = null;

    /* ── Restaurar estado: sidebar expandida ── */
    if (localStorage.getItem('sidebarExpanded') === 'true') {
        sidebar.classList.add('expanded');
        body.classList.add('sidebar-expanded');
    }

    document.getElementById('sidebarToggle').addEventListener('click', function () {
        var expanded = sidebar.classList.toggle('expanded');
        body.classList.toggle('sidebar-expanded', expanded);
        localStorage.setItem('sidebarExpanded', expanded);
        if (!expanded) hideFlyout();
    });

    /* ── Grupos expansíveis (modo expandido) ── */
    var savedGroups = {};
    try { savedGroups = JSON.parse(localStorage.getItem('sbOpenGroups') || '{}'); } catch(e) {}

    document.querySelectorAll('.sidebar-group').forEach(function (grp) {
        var btn     = grp.querySelector('.sidebar-group-btn');
        var submenu = grp.querySelector('.sidebar-submenu');
        var grpId   = grp.id;

        /* Restaurar grupos abertos (sem sobrescrever o que o PHP já marcou como aberto) */
        if (savedGroups[grpId] && !submenu.classList.contains('open')) {
            submenu.classList.add('open');
            btn.classList.add('open');
        }

        /* Toggle ao clicar no cabeçalho do grupo */
        btn.addEventListener('click', function () {
            if (!sidebar.classList.contains('expanded')) return; /* collapsed: só flyout */
            var isOpen = submenu.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
            savedGroups[grpId] = isOpen;
            try { localStorage.setItem('sbOpenGroups', JSON.stringify(savedGroups)); } catch(e) {}
        });

        /* Flyout ao passar o mouse (modo recolhido) */
        btn.addEventListener('mouseenter', function () {
            if (sidebar.classList.contains('expanded')) return;
            clearTimeout(flyoutTimer);
            showFlyout(btn, submenu);
        });
        btn.addEventListener('mouseleave', function () {
            flyoutTimer = setTimeout(hideFlyout, 180);
        });
    });

    flyout.addEventListener('mouseenter', function () { clearTimeout(flyoutTimer); });
    flyout.addEventListener('mouseleave', function () { flyoutTimer = setTimeout(hideFlyout, 180); });

    /* ── Flyout helpers ── */
    function showFlyout(btn, submenu) {
        var rect   = btn.getBoundingClientRect();
        var title  = btn.querySelector('span') ? btn.querySelector('span').textContent.trim() : '';
        var links  = submenu.querySelectorAll('.sidebar-sublink');

        flyout.querySelector('.sb-flyout-title').textContent = title;
        var bodyEl = flyout.querySelector('.sb-flyout-body');
        bodyEl.innerHTML = '';

        links.forEach(function (link) {
            var a = document.createElement('a');
            a.href = link.href;
            a.textContent = link.textContent.trim();
            if (link.classList.contains('active')) a.classList.add('active');
            bodyEl.appendChild(a);
        });

        flyout.style.display = 'block';
        flyout.style.top     = rect.top + 'px';

        /* Ajuste vertical se sair da tela */
        var flyH = flyout.offsetHeight;
        if (rect.top + flyH > window.innerHeight) {
            flyout.style.top = (window.innerHeight - flyH - 8) + 'px';
        }
    }

    function hideFlyout() {
        flyout.style.display = 'none';
    }
})();
</script>
