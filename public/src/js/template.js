// Template ExcavaTrack - JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    
    // Sidebar Toggle Function
    function initSidebarToggle() {
        const toggleBtn = document.querySelector('.toggle-sidebar-btn');
        const body = document.body;
        
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                body.classList.toggle('toggle-sidebar');
                
                // Save state in localStorage
                const isToggled = body.classList.contains('toggle-sidebar');
                localStorage.setItem('sidebar-toggled', isToggled);
            });
        }
        
        // Restore sidebar state
        const savedState = localStorage.getItem('sidebar-toggled');
        if (savedState === 'true') {
            body.classList.add('toggle-sidebar');
        }
    }
    
    // Active Menu Highlighting
    function highlightActiveMenu() {
        const currentPath = window.location.pathname;
        const baseUrl = BASE || '';
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            if (href) {
                const linkPath = href.replace(baseUrl, '');
                if (currentPath.includes(linkPath) && linkPath !== '') {
                    link.classList.add('active');
                    link.classList.remove('collapsed');
                    
                    // Open parent accordion if it's a submenu item
                    const parentCollapse = link.closest('.nav-content');
                    if (parentCollapse) {
                        parentCollapse.classList.add('show');
                        const parentToggle = document.querySelector(`[data-bs-target="#${parentCollapse.id}"]`);
                        if (parentToggle) {
                            parentToggle.classList.remove('collapsed');
                        }
                    }
                }
            }
        });
    }
    
    // Enhanced DataTables Initialization
    function initDataTables() {
        if (typeof $ !== 'undefined' && $.fn.DataTable) {
            $('.table').each(function() {
                const table = $(this);
                
                if (!table.hasClass('dataTable')) {
                    table.DataTable({
                        responsive: true,
                        pageLength: 25,
                        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip',
                        buttons: [
                            {
                                extend: 'excel',
                                text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                                className: 'btn btn-success btn-sm me-2'
                            },
                            {
                                extend: 'pdf',
                                text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                                className: 'btn btn-danger btn-sm me-2'
                            },
                            {
                                extend: 'print',
                                text: '<i class="bi bi-printer"></i> Imprimir',
                                className: 'btn btn-secondary btn-sm'
                            }
                        ],
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.11.5/i18n/pt-BR.json'
                        }
                    });
                }
            });
        }
    }
    
    // Smooth Animations
    function initAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-up');
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.card, .dashboard-card, .alert').forEach(el => {
            observer.observe(el);
        });
    }
    
    // Form Enhancements
    function enhanceForms() {
        // Auto-focus first input in modals
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                const firstInput = modal.querySelector('input, textarea, select');
                if (firstInput) {
                    firstInput.focus();
                }
            });
        });
        
        // Form validation styling
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
    }
    
    // Toast Notifications
    function initToasts() {
        const toastElements = document.querySelectorAll('.toast');
        toastElements.forEach(toastEl => {
            const toast = new bootstrap.Toast(toastEl);
            toast.show();
        });
    }
    
    // Search Functionality
    function initSearch() {
        const searchInputs = document.querySelectorAll('[data-search]');
        
        searchInputs.forEach(input => {
            const targetSelector = input.getAttribute('data-search');
            const targets = document.querySelectorAll(targetSelector);
            
            input.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                
                targets.forEach(target => {
                    const text = target.textContent.toLowerCase();
                    const shouldShow = text.includes(searchTerm);
                    target.style.display = shouldShow ? '' : 'none';
                });
            });
        });
    }
    
    // Tooltips and Popovers
    function initTooltips() {
        // Initialize Bootstrap tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Initialize Bootstrap popovers
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }
    
    // Counter Animation
    function animateCounters() {
        const counters = document.querySelectorAll('.stats-number');
        
        counters.forEach(counter => {
            const updateCounter = () => {
                const target = parseInt(counter.textContent.replace(/[^\d]/g, ''));
                const count = +counter.getAttribute('data-count') || 0;
                const increment = target / 200;
                
                if (count < target) {
                    counter.setAttribute('data-count', Math.ceil(count + increment));
                    const currentValue = Math.ceil(count + increment);
                    
                    // Preserve currency symbol if exists
                    if (counter.textContent.includes('R$')) {
                        counter.textContent = 'R$ ' + currentValue.toLocaleString('pt-BR');
                    } else {
                        counter.textContent = currentValue.toLocaleString('pt-BR');
                    }
                    
                    setTimeout(updateCounter, 10);
                } else {
                    if (counter.textContent.includes('R$')) {
                        counter.textContent = 'R$ ' + target.toLocaleString('pt-BR');
                    } else {
                        counter.textContent = target.toLocaleString('pt-BR');
                    }
                }
            };
            
            // Start animation when element is visible
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        updateCounter();
                        observer.unobserve(counter);
                    }
                });
            });
            
            observer.observe(counter);
        });
    }
    
    // Initialize all functions
    initSidebarToggle();
    highlightActiveMenu();
    initDataTables();
    initAnimations();
    enhanceForms();
    initToasts();
    initSearch();
    initTooltips();
    animateCounters();
    
    // Custom Events
    document.addEventListener('templateReady', function() {
        console.log('ExcavaTrack Template carregado com sucesso!');
    });
    
    // Dispatch template ready event
    setTimeout(() => {
        document.dispatchEvent(new Event('templateReady'));
    }, 100);
});

// Captura erros globais e exibe via toast
window.addEventListener('error', function(event) {
    if (window.ExcavaTrack && typeof window.ExcavaTrack.showError === 'function') {
        window.ExcavaTrack.showError('Erro: ' + event.message);
    }
});

// Utility Functions
window.ExcavaTrack = {
    // Show success notification
    showSuccess: function(message) {
        this.showToast(message, 'success');
    },
    
    // Show error notification
    showError: function(message) {
        this.showToast(message, 'danger');
    },
    
    // Show warning notification
    showWarning: function(message) {
        this.showToast(message, 'warning');
    },
    
    // Generic toast function
    showToast: function(message, type = 'info') {
        const toastContainer = document.querySelector('.toast-container') || this.createToastContainer();
        
        const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        
        toastContainer.insertAdjacentHTML('beforeend', toastHtml);
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement);
        toast.show();
        
        // Remove toast element after it's hidden
        toastElement.addEventListener('hidden.bs.toast', function() {
            toastElement.remove();
        });
    },
    
    // Create toast container if it doesn't exist
    createToastContainer: function() {
        const container = document.createElement('div');
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '11';
        document.body.appendChild(container);
        return container;
    },
    
    // Confirm dialog
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    },
    
    // Format currency
    formatCurrency: function(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    },
    
    // Format date
    formatDate: function(date) {
        return new Intl.DateTimeFormat('pt-BR').format(new Date(date));
    },
    
    // Função utilitária para validar o ID do recibo
    validateReciboId: function(reciboId) {
        if (!reciboId || reciboId.trim() === '') {
            this.showError('ID do recibo não informado');
            return false;
        }
        return true;
    }
};

// Exemplo de função que processa recibo
function processarRecibo() {
    const reciboId = document.getElementById('reciboId').value;
    if (!window.ExcavaTrack.validateReciboId(reciboId)) return;

    // ...continua o processamento do recibo...
}