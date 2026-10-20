import './bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';
import * as bootstrap from 'bootstrap';
import Chart from 'chart.js/auto';

window.bootstrap = bootstrap;
window.Chart = Chart;

/** Printing: open every "Show as table" so the numbers print with the charts. */
window.addEventListener('beforeprint', () => {
    document.querySelectorAll('details').forEach((d) => { d.dataset.wasOpen = d.open ? '1' : ''; d.open = true; });
});
window.addEventListener('afterprint', () => {
    document.querySelectorAll('details').forEach((d) => { d.open = d.dataset.wasOpen === '1'; });
});

/** Single-series line chart (trends across rating periods). */
window.eipcrLine = function (id, title, labels, values, max, decimals, suffix) {
    const canvas = document.getElementById(id);
    if (!canvas) {
        return;
    }
    suffix = suffix || '';

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                label: title,
                data: values,
                borderColor: '#256A2B',
                backgroundColor: '#256A2B',
                borderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
                tension: 0,
                spanGaps: true,
            }],
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => c.parsed.y === null ? `${title}: no data` : `${title}: ${Number(c.parsed.y).toFixed(decimals)}${suffix}` } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#28362B', font: { family: 'DM Sans' } } },
                y: {
                    beginAtZero: true,
                    max: max || undefined,
                    grid: { color: '#E7ECE8' },
                    border: { display: false },
                    ticks: { color: '#66736A', font: { family: 'DM Sans' }, callback: (v) => v + suffix },
                },
            },
        },
    });
};

/**
 * Single-series bar chart in the MMSU green. Pages call it after the DOM loads:
 * document.addEventListener('DOMContentLoaded', () => eipcrBar(...)).
 */
window.eipcrBar = function (id, title, labels, values, max, decimals) {
    const canvas = document.getElementById(id);
    if (!canvas) {
        return;
    }

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: title,
                data: values,
                backgroundColor: '#256A2B',
                hoverBackgroundColor: '#104D03',
                borderRadius: 4,
                borderSkipped: 'start',
                maxBarThickness: 44,
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: (c) => `${title}: ${Number(c.parsed.y).toFixed(decimals)}` } },
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#28362B', font: { family: 'DM Sans' } } },
                y: {
                    beginAtZero: true,
                    max: max || undefined,
                    grid: { color: '#E7ECE8' },
                    border: { display: false },
                    ticks: { color: '#66736A', precision: decimals ? undefined : 0, font: { family: 'DM Sans' } },
                },
            },
        },
    });
};
