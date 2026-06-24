<div
    x-data
    x-init="
        $el.querySelectorAll('pre').forEach(pre => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'code-copy-btn';
            btn.textContent = 'copiar';
            pre.appendChild(btn);
            btn.addEventListener('click', () => {
                const text = pre.querySelector('code')?.textContent ?? '';
                navigator.clipboard.writeText(text).then(() => {
                    btn.textContent = '✓ copiado';
                    btn.classList.add('copiado');
                    setTimeout(() => {
                        btn.textContent = 'copiar';
                        btn.classList.remove('copiado');
                    }, 2000);
                });
            });
        })
    "
    {{ $attributes->class([
        'prose prose-sm dark:prose-invert max-w-none',
        'prose-p:my-2 prose-headings:mt-4 prose-headings:mb-1',
        'prose-li:my-0.5 prose-ul:my-2 prose-ol:my-2',
        'prose-pre:text-xs prose-pre:leading-relaxed prose-pre:rounded-xl',
        'prose-code:before:content-none prose-code:after:content-none prose-code:font-normal',
        'prose-a:text-[var(--color-accent)] prose-a:no-underline hover:prose-a:underline',
        'prose-strong:font-semibold',
    ]) }}>
    {!! $html !!}
</div>
