import Chart from 'chart.js/auto';
window.Chart = Chart;

import mermaid from 'mermaid';
mermaid.initialize({ startOnLoad: false, theme: 'default' });
window.mermaid = mermaid;
