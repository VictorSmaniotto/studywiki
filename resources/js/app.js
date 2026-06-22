import Chart from 'chart.js/auto';
window.Chart = Chart;

import mermaid from 'mermaid';
mermaid.initialize({ startOnLoad: false, theme: 'default' });
window.mermaid = mermaid;

// NativePHP desktop: atalho CmdOrCtrl+G ("Focar em Gerar"). `window.Native` só
// existe dentro do app Electron; no navegador o bloco é ignorado.
if (window.Native?.on) {
    window.Native.on('focus-gerar', () => {
        const alvo = document.querySelector('[data-gerar-foco]');
        if (alvo) {
            alvo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            alvo.focus();
        }
    });
}
