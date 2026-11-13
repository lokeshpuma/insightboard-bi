/**
 * InsightBoard - Core Frontend Application Helpers
 */

document.addEventListener('DOMContentLoaded', () => {
    // Dropdown Toggles
    document.querySelectorAll('[data-toggle="dropdown"]').forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const targetId = trigger.getAttribute('data-target');
            const menu = targetId ? document.getElementById(targetId) : trigger.nextElementSibling;
            if (menu) {
                // Close other menus
                document.querySelectorAll('.dropdown-menu.show').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });
                menu.classList.toggle('show');
            }
        });
    });

    // Close dropdowns on outside click
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show');
        });
    });

    // Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
    }

    // Modal Close Triggers
    document.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-backdrop');
            if (modal) modal.style.display = 'none';
        });
    });
});

// Modal Global Opener
window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
    }
};

window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
};

/**
 * Chart Registry & Helper Module
 */
window.InsightCharts = {
    instances: {},

    colors: [
        '#4f46e5', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', 
        '#8b5cf6', '#3b82f6', '#14b8a6', '#f97316', '#6366f1'
    ],

    render(canvasId, config) {
        const canvas = document.getElementById(canvasId);
        if (!canvas) return null;

        // Destroy existing chart instance on canvas
        if (this.instances[canvasId]) {
            this.instances[canvasId].destroy();
            delete this.instances[canvasId];
        }

        const ctx = canvas.getContext('2d');
        const chartType = config.chart_type || 'bar';
        const labels = config.labels || [];
        const rawSeries = config.series || [];

        // Build Chart.js datasets
        const datasets = rawSeries.map((s, idx) => {
            const color = this.colors[idx % this.colors.length];
            const isPieOrDoughnut = ['pie', 'doughnut'].includes(chartType);

            return {
                label: s.name || `Series ${idx + 1}`,
                data: s.data || [],
                backgroundColor: isPieOrDoughnut 
                    ? this.colors.slice(0, labels.length) 
                    : (chartType === 'area' ? `${color}33` : (chartType === 'line' ? color : color)),
                borderColor: isPieOrDoughnut ? '#ffffff' : color,
                borderWidth: chartType === 'line' || chartType === 'area' ? 2 : 1,
                fill: chartType === 'area',
                tension: 0.35,
                pointBackgroundColor: color,
                pointRadius: chartType === 'scatter' ? 5 : 3
            };
        });

        const effectiveType = chartType === 'area' ? 'line' : (chartType === 'horizontal_bar' ? 'bar' : chartType);
        const isHorizontal = chartType === 'horizontal_bar';

        const chartInstance = new Chart(ctx, {
            type: effectiveType,
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                indexAxis: isHorizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: ['pie', 'doughnut'].includes(chartType) || datasets.length > 1,
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            font: { family: '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto', size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#0f172a',
                        titleFont: { size: 12, weight: '600' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                scales: ['pie', 'doughnut'].includes(chartType) ? {} : {
                    x: {
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#64748b', font: { size: 11 } }
                    },
                    y: {
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#64748b', font: { size: 11 } }
                    }
                }
            }
        });

        this.instances[canvasId] = chartInstance;
        return chartInstance;
    }
};

