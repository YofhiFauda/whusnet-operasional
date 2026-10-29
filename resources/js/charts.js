import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;
window.dispatchEvent(new CustomEvent('chart:ready'));
