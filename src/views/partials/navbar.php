<?php
/** @var bool   $is_admin */
/** @var array  $rotas_permitidas */
/** @var string $base */
/** @var string $pageActive */
/** @var string $user_login */
/** @var array  $empresa */
$acessoComissao    = $is_admin || in_array('comissao',    $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoCd          = $is_admin || in_array('cd',          $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoFaturamento = $is_admin || in_array('faturamento', $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoPedidos     = $is_admin || in_array('pedidos',     $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoProcesso    = $is_admin || in_array('processo',    $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoCarga       = $is_admin || in_array('carga',       $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoPcp         = $is_admin || in_array('pcp',         $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoPd          = $is_admin || in_array('pd',          $rotas_permitidas) || in_array('*', $rotas_permitidas);
$acessoQualidade   = $is_admin || in_array('qualidade',   $rotas_permitidas) || in_array('*', $rotas_permitidas);

$pActive = $pageActive ?? '';
$activeGroup = match(true) {
    str_starts_with($pActive, 'comissao-') || $pActive === 'comissao'                                => 'comissao',
    str_starts_with($pActive, 'cd-') || in_array($pActive, ['dashboard', 'calendario'])              => 'cd',
    str_starts_with($pActive, 'faturamento-') || in_array($pActive, ['faturamento', 'meta-empresa', 'faturamento-eficiencia-uep']) => 'faturamento',
    str_starts_with($pActive, 'pedidos-')                                                            => 'pedidos',
    str_starts_with($pActive, 'processo-')                                                           => 'processo',
    str_starts_with($pActive, 'carga-')                                                              => 'carga',
    str_starts_with($pActive, 'pcp-')                                                               => 'pcp',
    str_starts_with($pActive, 'pd-')                                                                => 'pd',
    str_starts_with($pActive, 'qualidade-')                                                         => 'qualidade',
    $pActive === 'permissao'                                                                         => 'permissao',
    default => ''
};

$comissaoSub = match(true) {
    $pActive === 'comissao-dashboard'                                                   => 'dashboard',
    str_starts_with($pActive, 'comissao-relatorio') || $pActive === 'comissao-extrato-analitico' => 'relatorios',
    str_starts_with($pActive, 'comissao-')                                              => 'cadastros',
    default => ''
};
$cdSub = match(true) {
    in_array($pActive, ['cd-dashboard', 'dashboard'])   => 'dashboard',
    in_array($pActive, ['cd-calendario', 'calendario']) => 'calendario',
    default => ''
};
$cargaSub = match(true) {
    $pActive === 'carga-projecao' => 'projecao',
    default => ''
};
$pcpSub = match(true) {
    $pActive === 'pcp-relatorio-caixote'    => 'relatorio-caixote',
    $pActive === 'pcp-relatorio-producao'   => 'relatorio-producao',
    $pActive === 'pcp-relatorio-pillow'     => 'relatorio-pillow',
    $pActive === 'pcp-relatorio-fpt'        => 'relatorio-fpt',
    $pActive === 'pcp-relatorio-mesa-faixa' => 'relatorio-mesa-faixa',
    $pActive === 'pcp-relatorio-optron'     => 'relatorio-optron',
    $pActive === 'pcp-relatorio-tampo-liso'    => 'relatorio-tampo-liso',
    $pActive === 'pcp-relatorio-tampo-bordado'      => 'relatorio-tampo-bordado',
    $pActive === 'pcp-relatorio-tampo-bordado-mesa' => 'relatorio-tampo-bordado-mesa',
    $pActive === 'pcp-relatorio-manta'              => 'relatorio-manta',
    $pActive === 'pcp-relatorio-manta-mesa'         => 'relatorio-manta-mesa',
    $pActive === 'pcp-relatorio-mesa-de-corte'      => 'relatorio-mesa-de-corte',
    $pActive === 'pcp-relatorio-rolo-bordado'       => 'relatorio-rolo-bordado',
    $pActive === 'pcp-relatorio-disco-de-corte'    => 'relatorio-disco-de-corte',
    $pActive === 'pcp-relatorio-tapecaria'          => 'relatorio-tapecaria',
    $pActive === 'pcp-relatorio-robotec'            => 'relatorio-robotec',
    $pActive === 'pcp-relatorio-conjugado'          => 'relatorio-conjugado',
    $pActive === 'pcp-relatorio-trave-peze'         => 'relatorio-trave-peze',
    $pActive === 'pcp-relatorio-molas-bordas'       => 'relatorio-molas-bordas',
    $pActive === 'pcp-relatorio-pcp-molas'          => 'relatorio-pcp-molas',
    $pActive === 'pcp-relatorio-pcp-tampo'          => 'relatorio-pcp-tampo',
    $pActive === 'pcp-relatorio-pcp-borda-aco'      => 'relatorio-pcp-borda-aco',
    $pActive === 'pcp-relatorio-pcp-expedicao-rolo' => 'relatorio-pcp-expedicao-rolo',
    $pActive === 'pcp-relatorio-pcp-cordao'         => 'relatorio-pcp-cordao',
    $pActive === 'pcp-relatorio-caixa-box'               => 'relatorio-caixa-box',
    $pActive === 'pcp-relatorio-robotec-abastecedor'     => 'relatorio-robotec-abastecedor',
    $pActive === 'pcp-relatorio-vertical-espuma'         => 'relatorio-vertical-espuma',
    $pActive === 'pcp-relatorio-horizontal-espuma'       => 'relatorio-horizontal-espuma',
    $pActive === 'pcp-resumo-lote'                       => 'resumo-lote',
    default => ''
};
// Subgrupos ativos do PCP para abrir o 2º nível automaticamente
$pcpToSubGrp = [
    'relatorio-caixote'             => ['robotec', 'caixote'],
    'relatorio-producao'            => ['robotec', 'costura', 'caixa-box'],
    'relatorio-pillow'              => ['costura'],
    'relatorio-fpt'                 => ['costura'],
    'relatorio-mesa-faixa'          => ['costura'],
    'relatorio-optron'              => ['costura'],
    'relatorio-tampo-liso'          => ['costura'],
    'relatorio-tampo-bordado'       => ['robotec', 'bordadeira'],
    'relatorio-manta'               => ['robotec', 'laminacao'],
    'relatorio-vertical-espuma'     => ['laminacao'],
    'relatorio-horizontal-espuma'   => ['laminacao'],
    'relatorio-mesa-de-corte'       => ['costura'],
    'relatorio-rolo-bordado'        => ['costura', 'bordadeira'],
    'relatorio-disco-de-corte'     => ['costura'],
    'relatorio-molas-bordas'        => ['caixote'],
    'relatorio-pcp-molas'           => ['pcp-molas'],
    'relatorio-pcp-cordao'          => ['pcp-molas'],
    'relatorio-pcp-tampo'           => ['pcp-molas'],
    'relatorio-pcp-borda-aco'       => ['pcp-molas'],
    'relatorio-pcp-expedicao-rolo'  => ['pcp-molas'],
    'relatorio-caixa-box'           => ['caixa-box'],
    'relatorio-robotec-abastecedor' => ['robotec'],
];
$pcpSGs      = $pcpToSubGrp[$pcpSub] ?? [];
$sgRobotec   = in_array('robotec',   $pcpSGs);
$sgCaixote   = in_array('caixote',   $pcpSGs);
$sgCostura   = in_array('costura',   $pcpSGs);
$sgBordadeira= in_array('bordadeira',$pcpSGs);
$sgLaminacao = in_array('laminacao', $pcpSGs);
$sgCaixaBox  = in_array('caixa-box', $pcpSGs);
$sgPcpMolas  = in_array('pcp-molas', $pcpSGs);

$fatSub = match(true) {
    in_array($pActive, ['faturamento-dashboard', 'faturamento']) => 'dashboard',
    $pActive === 'meta-empresa'                                  => 'metas',
    $pActive === 'faturamento-programacao'                       => 'programacao',
    default => ''
};
$pedidosSub = match(true) {
    $pActive === 'pedidos-transferencia' => 'transferencia',
    default => ''
};
$processoSub = match(true) {
    $pActive === 'processo-troca-almox'              => 'troca-almox',
    $pActive === 'processo-troca-almox-carga'        => 'troca-almox-carga',
    $pActive === 'processo-troca-almox-pedido'       => 'troca-almox-pedido',
    $pActive === 'processo-troca-tipo-nf'            => 'troca-tipo-nf',
    $pActive === 'processo-transferencia-estoque'    => 'transferencia-estoque',
    $pActive === 'processo-relatorio-mov-estoque'                 => 'relatorio-mov-estoque',
    $pActive === 'processo-relatorio-mov-estoque-refugo'          => 'relatorio-mov-estoque-refugo',
    $pActive === 'processo-relatorio-mov-estoque-variacao-custo'  => 'relatorio-mov-estoque-variacao-custo',
    $pActive === 'processo-relatorio-variacao-taxa-ggf'           => 'relatorio-variacao-taxa-ggf',
    $pActive === 'processo-relatorio-consumo-thermoplast'         => 'relatorio-consumo-thermoplast',
    $pActive === 'processo-relatorio-consumo-demanda-espuma'      => 'relatorio-consumo-demanda-espuma',
    $pActive === 'processo-relatorio-variacao-nfe'              => 'relatorio-variacao-nfe',
    default => ''
};
$processoRelSgAtivo = str_starts_with($processoSub, 'relatorio-');

$pdSub = match(true) {
    $pActive === 'pd-inativacao-preco' => 'inativacao-preco',
    default => ''
};

$qualidadeSub = match(true) {
    $pActive === 'qualidade-rastreabilidade-costura'         => 'rastreabilidade-costura',
    $pActive === 'qualidade-rastreabilidade-tampo-bordado'  => 'rastreabilidade-tampo-bordado',
    $pActive === 'qualidade-rastreabilidade-linha-montagem' => 'rastreabilidade-linha-montagem',
    $pActive === 'qualidade-rastreabilidade-molas'          => 'rastreabilidade-molas',
    $pActive === 'qualidade-rastreabilidade-cordao-molas'  => 'rastreabilidade-cordao-molas',
    $pActive === 'qualidade-rastreabilidade-borda-molas'   => 'rastreabilidade-borda-molas',
    $pActive === 'qualidade-rastreabilidade-fixacao-borda' => 'rastreabilidade-fixacao-borda',
    default => ''
};
$qualidadeRastrSgAtivo     = in_array($qualidadeSub, ['rastreabilidade-costura','rastreabilidade-tampo-bordado','rastreabilidade-linha-montagem']);
$qualidadeRastrMolaSgAtivo = in_array($qualidadeSub, ['rastreabilidade-molas','rastreabilidade-cordao-molas','rastreabilidade-borda-molas','rastreabilidade-fixacao-borda']);

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
                <li>
                    <a href="<?= $base ?>faturamento-eficiencia-uep"
                       class="sidebar-sublink <?= $pActive === 'faturamento-eficiencia-uep' ? 'active' : '' ?>">
                        Eficiência UEP
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoPedidos): ?>
        <li class="sidebar-group" id="grpPedidos">
            <button class="sidebar-group-btn <?= $activeGroup === 'pedidos' ? 'active open' : '' ?>">
                <i class="bi bi-bag-check"></i>
                <span>Pedidos</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'pedidos' ? 'open' : '' ?>">
                <li>
                    <a href="<?= $base ?>pedidos-transferencia"
                       class="sidebar-sublink <?= $pedidosSub === 'transferencia' ? 'active' : '' ?>">
                        Transferência
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoProcesso): ?>
        <li class="sidebar-group" id="grpProcesso">
            <button class="sidebar-group-btn <?= $activeGroup === 'processo' ? 'active open' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Processo</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'processo' ? 'open' : '' ?>">
                <li>
                    <a href="<?= $base ?>processo-troca-almox"
                       class="sidebar-sublink <?= $processoSub === 'troca-almox' ? 'active' : '' ?>">
                        Troca Almox. Ordem
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>processo-troca-almox-carga"
                       class="sidebar-sublink <?= $processoSub === 'troca-almox-carga' ? 'active' : '' ?>">
                        Troca Almox. Carga
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>processo-troca-almox-pedido"
                       class="sidebar-sublink <?= $processoSub === 'troca-almox-pedido' ? 'active' : '' ?>">
                        Troca Almox. Pedido
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>processo-troca-tipo-nf"
                       class="sidebar-sublink <?= $processoSub === 'troca-tipo-nf' ? 'active' : '' ?>">
                        Troca Tipo NF Ent.
                    </a>
                </li>
                <li>
                    <a href="<?= $base ?>processo-transferencia-estoque"
                       class="sidebar-sublink <?= $processoSub === 'transferencia-estoque' ? 'active' : '' ?>">
                        Transferência de Estoque
                    </a>
                </li>

                <!-- ── Relatórios ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $processoRelSgAtivo ? 'active open' : '' ?>">
                        <span>Relatórios</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $processoRelSgAtivo ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>processo-relatorio-mov-estoque"
                               class="pcp-sub-sublink <?= $processoSub === 'relatorio-mov-estoque' ? 'active' : '' ?>">
                            Mov. de Estoque Rateio</a></li>
                        <li><a href="<?= $base ?>processo-relatorio-mov-estoque-refugo"
                               class="pcp-sub-sublink <?= $processoSub === 'relatorio-mov-estoque-refugo' ? 'active' : '' ?>">
                            Mov. de Estoque Refugo</a></li>
                        <li><a href="<?= $base ?>processo-relatorio-mov-estoque-variacao-custo"
                               class="pcp-sub-sublink <?= $processoSub === 'relatorio-mov-estoque-variacao-custo' ? 'active' : '' ?>">
                            Mov. de Estoque Var. Custo</a></li>
                        <li><a href="<?= $base ?>processo-relatorio-variacao-taxa-ggf"
                               class="pcp-sub-sublink <?= $processoSub === 'relatorio-variacao-taxa-ggf' ? 'active' : '' ?>">
                            Variação Taxa GGF</a></li>
                        <li><a href="<?= $base ?>processo-relatorio-consumo-thermoplast"
                               class="pcp-sub-sublink <?= $processoSub === 'relatorio-consumo-thermoplast' ? 'active' : '' ?>">
                            Consumo de Thermoplast</a></li>
                        <li><a href="<?= $base ?>processo-relatorio-consumo-demanda-espuma"
                               class="pcp-sub-sublink <?= $processoSub === 'relatorio-consumo-demanda-espuma' ? 'active' : '' ?>">
                            Consumo Demanda Espuma</a></li>
                        <li><a href="<?= $base ?>processo-relatorio-variacao-nfe"
                               class="pcp-sub-sublink <?= $processoSub === 'relatorio-variacao-nfe' ? 'active' : '' ?>">
                            Variação NFE</a></li>
                    </ul>
                </li>

            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoCarga): ?>
        <li class="sidebar-group" id="grpCarga">
            <button class="sidebar-group-btn <?= $activeGroup === 'carga' ? 'active open' : '' ?>">
                <i class="bi bi-truck-flatbed"></i>
                <span>Cargas</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'carga' ? 'open' : '' ?>">
                <li>
                    <a href="<?= $base ?>carga-projecao"
                       class="sidebar-sublink <?= $cargaSub === 'projecao' ? 'active' : '' ?>">
                        Projeção de Carga
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoPcp): ?>
        <li class="sidebar-group" id="grpPcp">
            <button class="sidebar-group-btn <?= $activeGroup === 'pcp' ? 'active open' : '' ?>">
                <i class="bi bi-clipboard-data"></i>
                <span>PCP</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'pcp' ? 'open' : '' ?>">

                <li>
                    <a href="<?= $base ?>pcp-resumo-lote"
                       class="sidebar-sublink <?= $pcpSub === 'resumo-lote' ? 'active' : '' ?>">
                        <i class="bi bi-clipboard-data"></i> Resumo do Lote
                    </a>
                </li>

                <!-- ── ROBOTEC ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $sgRobotec ? 'active open' : '' ?>">
                        <span>Robotec</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $sgRobotec ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>pcp-relatorio-caixote"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-caixote' ? 'active' : '' ?>">
                            Seq. Caixote</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-producao"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-producao' ? 'active' : '' ?>">
                            Seq. Produção</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-tampo-bordado"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-tampo-bordado' ? 'active' : '' ?>">
                            Seq. Tampo Bordado</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-manta"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-manta' ? 'active' : '' ?>">
                            Seq. Manta</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-robotec-abastecedor"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-robotec-abastecedor' ? 'active' : '' ?>">
                            Seq. Robotec Abastecedor</a></li>
                    </ul>
                </li>

                <!-- ── CAIXOTE ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $sgCaixote ? 'active open' : '' ?>">
                        <span>Caixote</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $sgCaixote ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>pcp-relatorio-caixote"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-caixote' ? 'active' : '' ?>">
                            Seq. Caixote</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-molas-bordas"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-molas-bordas' ? 'active' : '' ?>">
                            Seq. Molas e Bordas</a></li>
                    </ul>
                </li>

                <!-- ── COSTURA ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $sgCostura ? 'active open' : '' ?>">
                        <span>Costura</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $sgCostura ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>pcp-relatorio-producao"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-producao' ? 'active' : '' ?>">
                            Seq. Produção</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-pillow"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-pillow' ? 'active' : '' ?>">
                            Seq. Pillow</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-fpt"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-fpt' ? 'active' : '' ?>">
                            Seq. FPT</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-mesa-faixa"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-mesa-faixa' ? 'active' : '' ?>">
                            Seq. Mesa de Faixa</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-optron"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-optron' ? 'active' : '' ?>">
                            Seq. Optron</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-tampo-liso"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-tampo-liso' ? 'active' : '' ?>">
                            Seq. Tampo Liso</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-mesa-de-corte"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-mesa-de-corte' ? 'active' : '' ?>">
                            Seq. Mesa de Corte</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-rolo-bordado"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-rolo-bordado' ? 'active' : '' ?>">
                            Seq. Rolo Bordado</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-disco-de-corte"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-disco-de-corte' ? 'active' : '' ?>">
                            Seq. Disco de Corte</a></li>
                    </ul>
                </li>

                <!-- ── BORDADEIRA ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $sgBordadeira ? 'active open' : '' ?>">
                        <span>Bordadeira</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $sgBordadeira ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>pcp-relatorio-tampo-bordado"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-tampo-bordado' ? 'active' : '' ?>">
                            Seq. Tampo Bordado</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-rolo-bordado"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-rolo-bordado' ? 'active' : '' ?>">
                            Seq. Rolo Bordado</a></li>
                    </ul>
                </li>

                <!-- ── LAMINAÇÃO ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $sgLaminacao ? 'active open' : '' ?>">
                        <span>Laminação</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $sgLaminacao ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>pcp-relatorio-manta"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-manta' ? 'active' : '' ?>">
                            Seq. Manta</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-vertical-espuma"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-vertical-espuma' ? 'active' : '' ?>">
                            Seq. Vertical Espuma</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-horizontal-espuma"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-horizontal-espuma' ? 'active' : '' ?>">
                            Seq. Horizontal Espuma</a></li>
                    </ul>
                </li>

                <!-- ── PCP MOLAS ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $sgPcpMolas ? 'active open' : '' ?>">
                        <span>PCP Molas</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $sgPcpMolas ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>pcp-relatorio-pcp-molas"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-pcp-molas' ? 'active' : '' ?>">
                            Qtde de Molinhas</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-pcp-cordao"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-pcp-cordao' ? 'active' : '' ?>">
                            Qtde de Cordão de Molas</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-pcp-tampo"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-pcp-tampo' ? 'active' : '' ?>">
                            Qtde de Tampo</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-pcp-borda-aco"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-pcp-borda-aco' ? 'active' : '' ?>">
                            Borda de Aço</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-pcp-expedicao-rolo"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-pcp-expedicao-rolo' ? 'active' : '' ?>">
                            Expd. Qtde de Rolo</a></li>
                    </ul>
                </li>

                <!-- ── CAIXA BOX ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $sgCaixaBox ? 'active open' : '' ?>">
                        <span>Caixa Box</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $sgCaixaBox ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>pcp-relatorio-producao"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-producao' ? 'active' : '' ?>">
                            Seq. Produção</a></li>
                        <li><a href="<?= $base ?>pcp-relatorio-caixa-box"
                               class="pcp-sub-sublink <?= $pcpSub === 'relatorio-caixa-box' ? 'active' : '' ?>">
                            Seq. Caixa Box</a></li>
                    </ul>
                </li>

            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoPd): ?>
        <li class="sidebar-group" id="grpPd">
            <button class="sidebar-group-btn <?= $activeGroup === 'pd' ? 'active open' : '' ?>">
                <i class="bi bi-lightbulb"></i>
                <span>P&amp;D</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'pd' ? 'open' : '' ?>">
                <li>
                    <a href="<?= $base ?>pd-inativacao-preco"
                       class="sidebar-sublink <?= $pdSub === 'inativacao-preco' ? 'active' : '' ?>">
                        Inativação de Preço
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <?php if ($acessoQualidade): ?>
        <li class="sidebar-group" id="grpQualidade">
            <button class="sidebar-group-btn <?= $activeGroup === 'qualidade' ? 'active open' : '' ?>">
                <i class="bi bi-shield-check"></i>
                <span>Qualidade</span>
                <i class="bi bi-chevron-down group-chevron"></i>
            </button>
            <ul class="sidebar-submenu <?= $activeGroup === 'qualidade' ? 'open' : '' ?>">

                <!-- ── Rastreabilidade ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $qualidadeRastrSgAtivo ? 'active open' : '' ?>">
                        <span>Rastreabilidade</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $qualidadeRastrSgAtivo ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>qualidade-rastreabilidade-costura"
                               class="pcp-sub-sublink <?= $qualidadeSub === 'rastreabilidade-costura' ? 'active' : '' ?>">
                            Tampo Liso</a></li>
                        <li><a href="<?= $base ?>qualidade-rastreabilidade-tampo-bordado"
                               class="pcp-sub-sublink <?= $qualidadeSub === 'rastreabilidade-tampo-bordado' ? 'active' : '' ?>">
                            Tampo Bordado</a></li>
                        <li><a href="<?= $base ?>qualidade-rastreabilidade-linha-montagem"
                               class="pcp-sub-sublink <?= $qualidadeSub === 'rastreabilidade-linha-montagem' ? 'active' : '' ?>">
                            Linha de Montagem</a></li>
                    </ul>
                </li>

                <!-- ── RASTREABILIDADE MOLA ── -->
                <li class="pcp-subgroup">
                    <button class="pcp-subgroup-btn <?= $qualidadeRastrMolaSgAtivo ? 'active open' : '' ?>">
                        <span>Rastreabilidade Mola</span>
                        <i class="bi bi-chevron-down subgrp-chevron"></i>
                    </button>
                    <ul class="pcp-sub-submenu <?= $qualidadeRastrMolaSgAtivo ? 'open' : '' ?>">
                        <li><a href="<?= $base ?>qualidade-rastreabilidade-molas"
                               class="pcp-sub-sublink <?= $qualidadeSub === 'rastreabilidade-molas' ? 'active' : '' ?>">
                            Fabricação Molinhas</a></li>
                        <li><a href="<?= $base ?>qualidade-rastreabilidade-cordao-molas"
                               class="pcp-sub-sublink <?= $qualidadeSub === 'rastreabilidade-cordao-molas' ? 'active' : '' ?>">
                            Cordão de Molas</a></li>
                        <li><a href="<?= $base ?>qualidade-rastreabilidade-borda-molas"
                               class="pcp-sub-sublink <?= $qualidadeSub === 'rastreabilidade-borda-molas' ? 'active' : '' ?>">
                            Borda de Aço</a></li>
                        <li><a href="<?= $base ?>qualidade-rastreabilidade-fixacao-borda"
                               class="pcp-sub-sublink <?= $qualidadeSub === 'rastreabilidade-fixacao-borda' ? 'active' : '' ?>">
                            Fixação de Borda</a></li>
                    </ul>
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
.sidebar-submenu.open { max-height: 1400px; }
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

/* ─── PCP Subgrupos (2º nível) ─────────── */
.pcp-subgroup { margin: 0; }
.pcp-subgroup-btn {
    display: flex; align-items: center;
    width: calc(100% - 16px); margin: 1px 8px;
    height: 34px; border-radius: 8px;
    border: none; background: none; cursor: pointer;
    color: #6b7280; white-space: nowrap; overflow: hidden;
    transition: background 0.15s, color 0.15s;
    padding: 0 6px 0 46px;
    font-size: 0.78rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.05em;
}
.pcp-subgroup-btn > span { flex: 1; text-align: left; }
.pcp-subgroup-btn .subgrp-chevron {
    font-size: 0.68rem; flex-shrink: 0;
    transition: transform 0.2s ease;
}
.pcp-subgroup-btn.open .subgrp-chevron { transform: rotate(180deg); }
.pcp-subgroup-btn:hover { background: #f3f4f6; color: var(--sb-accent); }
.pcp-subgroup-btn.active { color: var(--sb-accent); }

.pcp-sub-submenu {
    list-style: none; padding: 0; margin: 0;
    max-height: 0; overflow: hidden;
    transition: max-height 0.22s ease;
}
.pcp-sub-submenu.open { max-height: 600px; }

.pcp-sub-sublink {
    display: block;
    padding: 7px 12px 7px 66px;
    margin: 1px 8px;
    border-radius: 8px;
    color: #6b7280; text-decoration: none;
    font-size: 0.83rem; font-weight: 400;
    white-space: nowrap;
    transition: background 0.15s, color 0.15s;
    position: relative;
}
.pcp-sub-sublink::before {
    content: '';
    position: absolute;
    left: 46px; top: 50%; transform: translateY(-50%);
    width: 5px; height: 5px; border-radius: 50%;
    background: #e5e7eb;
    transition: background 0.15s;
}
.pcp-sub-sublink:hover { background: #f3f4f6; color: var(--sb-accent); }
.pcp-sub-sublink:hover::before { background: var(--sb-accent); }
.pcp-sub-sublink.active { color: var(--sb-accent); font-weight: 600; }
.pcp-sub-sublink.active::before { background: var(--sb-accent); }

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
.sb-flyout-group-hdr {
    display: flex; align-items: center;
    font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.06em;
    color: #9ca3af;
    padding: 7px 10px 3px 12px;
    margin-top: 2px;
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.15s, color 0.15s;
    user-select: none;
}
.sb-flyout-group-hdr > span { flex: 1; }
.sb-flyout-group-hdr:hover { background: #f3f4f6; color: #374151; }
.sb-fghdr-chev {
    font-size: 0.62rem; flex-shrink: 0;
    transition: transform 0.2s ease;
}
.sb-flyout-group-hdr.open .sb-fghdr-chev { transform: rotate(180deg); }
.sb-flyout-sublinks {
    max-height: 0; overflow: hidden;
    transition: max-height 0.22s ease;
}
.sb-flyout-sublinks.open { max-height: 400px; }

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

    /* ── PCP Subgrupos (2º nível) ── */
    document.querySelectorAll('.pcp-subgroup-btn').forEach(function (btn) {
        var subMenu = btn.nextElementSibling;
        btn.addEventListener('click', function () {
            var isOpen = subMenu.classList.toggle('open');
            btn.classList.toggle('open', isOpen);
        });
    });

    /* ── Flyout helpers ── */
    function showFlyout(btn, submenu) {
        var rect  = btn.getBoundingClientRect();
        var title = btn.querySelector('span') ? btn.querySelector('span').textContent.trim() : '';

        flyout.querySelector('.sb-flyout-title').textContent = title;
        var bodyEl = flyout.querySelector('.sb-flyout-body');
        bodyEl.innerHTML = '';

        var subgroups = submenu.querySelectorAll('.pcp-subgroup');
        if (subgroups.length > 0) {
            /* Links diretos (sidebar-sublink) antes dos subgrupos */
            submenu.querySelectorAll(':scope > li > .sidebar-sublink').forEach(function (link) {
                var a = document.createElement('a');
                a.href = link.href;
                a.textContent = link.textContent.trim();
                if (link.classList.contains('active')) a.classList.add('active');
                bodyEl.appendChild(a);
            });

            /* 2 níveis: cabeçalho clicável (toggle) + links */
            subgroups.forEach(function (sg) {
                var sgBtn    = sg.querySelector('.pcp-subgroup-btn');
                var label    = sgBtn && sgBtn.querySelector('span') ? sgBtn.querySelector('span').textContent.trim() : '';
                var hasActive = !!sg.querySelector('.pcp-sub-sublink.active');

                var hdr = document.createElement('div');
                hdr.className = 'sb-flyout-group-hdr' + (hasActive ? ' open' : '');
                hdr.innerHTML = '<span>' + label + '</span><i class="bi bi-chevron-down sb-fghdr-chev"></i>';

                var linksWrap = document.createElement('div');
                linksWrap.className = 'sb-flyout-sublinks' + (hasActive ? ' open' : '');

                sg.querySelectorAll('.pcp-sub-sublink').forEach(function (link) {
                    var a = document.createElement('a');
                    a.href = link.href;
                    a.textContent = link.textContent.trim();
                    if (link.classList.contains('active')) a.classList.add('active');
                    linksWrap.appendChild(a);
                });

                hdr.addEventListener('click', function () {
                    var isOpen = linksWrap.classList.toggle('open');
                    hdr.classList.toggle('open', isOpen);
                });

                bodyEl.appendChild(hdr);
                bodyEl.appendChild(linksWrap);
            });
        } else {
            submenu.querySelectorAll('.sidebar-sublink').forEach(function (link) {
                var a = document.createElement('a');
                a.href = link.href;
                a.textContent = link.textContent.trim();
                if (link.classList.contains('active')) a.classList.add('active');
                bodyEl.appendChild(a);
            });
        }

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
