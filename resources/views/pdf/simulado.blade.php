<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
/*
 * DomPDF 3.x — padrão para header/footer:
 *   @page define a margem reservada para o header/footer
 *   position:fixed com top/bottom NEGATIVOS entra nessa margem reservada
 */

@@page {
    margin-top: 56px;
    margin-bottom: 44px;
    margin-left: 24px;
    margin-right: 24px;
}

* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10pt;
    color: #1a1a1a;
    line-height: 1.45;
    padding-left: 20px;
    padding-right: 20px;
}

/* ── Header fixo (E7): entra na margem @page ── */
.page-header {
    position: fixed;
    top: -50px;
    left: 0;
    right: 0;
    height: 42px;
    padding: 0 0 6px 0;
    border-bottom: 2px solid #2563eb;
}
.header-inner {
    width: 100%;
    display: table;
}
.header-left, .header-right {
    display: table-cell;
    vertical-align: bottom;
}
.header-right { text-align: right; font-size: 8pt; color: #555; }
.header-title  { font-weight: bold; font-size: 12pt; color: #1e3a5f; }
.header-sub    { font-size: 8.5pt; color: #555; margin-top: 1px; }

/* ── Footer fixo (E7) ── */
.page-footer {
    position: fixed;
    bottom: -40px;
    left: 0;
    right: 0;
    height: 34px;
    padding: 5px 0 0;
    border-top: 1px solid #d1d5db;
    font-size: 8pt;
    color: #6b7280;
}
.footer-inner {
    width: 100%;
    display: table;
}
.footer-left, .footer-right {
    display: table-cell;
    vertical-align: top;
}
.footer-right { text-align: right; }
.footer-sources { font-size: 7.5pt; margin-top: 2px; }

.pagenum:before { content: counter(page); }

/* ── Títulos de seção ── */
.section-title {
    background: #2563eb;
    color: #fff;
    padding: 5px 10px;
    font-size: 11pt;
    font-weight: bold;
    margin: 16px 0 12px;
    border-radius: 3px;
    page-break-after: avoid;
}

/* ── Questão ── */
.question {
    margin-bottom: 16px;
    page-break-inside: avoid;
}
.question-num {
    font-weight: bold;
    color: #1e3a5f;
    font-size: 10pt;
    margin-bottom: 3px;
}
.question-context {
    font-style: italic;
    color: #555;
    font-size: 9pt;
    margin-bottom: 3px;
}
.question-text {
    margin-bottom: 6px;
    font-size: 10pt;
}

/* ── Alternativas ── */
.option {
    margin-bottom: 2px;
    font-size: 9.5pt;
    padding-left: 6px;
}
.correct { color: #15803d; font-weight: bold; }
.wrong   { color: #dc2626; text-decoration: line-through; }

/* ── Comentário / rubrica ── */
.comment-box {
    margin-top: 7px;
    padding: 5px 8px;
    background: #eff6ff;
    border-left: 3px solid #2563eb;
    font-size: 9pt;
}
.comment-row { margin-bottom: 2px; }
.rubrica-box {
    margin: 6px 0;
    padding: 5px 8px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    font-size: 8.5pt;
}

/* ── Espaço dissertativa prova em branco ── */
.blank-area {
    height: 72px;
    border: 1px dashed #9ca3af;
    margin-top: 6px;
    border-radius: 2px;
}

/* ── Score box ── */
.score-box {
    background: #f0fdf4;
    border: 1px solid #86efac;
    padding: 7px 10px;
    margin-bottom: 12px;
    border-radius: 3px;
    font-size: 10pt;
}

/* ── Fontes ── */
.question-sources {
    margin-top: 5px;
    font-size: 8pt;
    color: #6b7280;
}

/* ── Separador de seção ── */
.section-break { page-break-before: always; }
</style>
</head>
<body>

{{-- ── Header fixo (E7) ─────────────────────────────────────────────────── --}}
<div class="page-header">
    <div class="header-inner">
        <div class="header-left">
            <div class="header-title">
                {{ ucfirst($disciplina) }}@if($perfil && $perfil !== 'personalizado') &nbsp;·&nbsp; {{ ucfirst($perfil) }}@endif
            </div>
            <div class="header-sub">
                Gerado em {{ $geracao->created_at->format('d/m/Y H:i') }}
                @if(($geracao->escopo['tempo_estimado_segundos'] ?? 0) > 0) &nbsp;·&nbsp; {{ floor($geracao->escopo['tempo_estimado_segundos'] / 60) }} min @endif
            </div>
        </div>
        <div class="header-right">#{{ $geracao->id }}</div>
    </div>
</div>

{{-- ── Footer fixo (E7) ─────────────────────────────────────────────────── --}}
<div class="page-footer">
    <div class="footer-inner">
        <div class="footer-left">
            StudyWiki
            @if($fontesPaginas->isNotEmpty())
                <div class="footer-sources">
                    Fontes: {{ $fontesPaginas->map(fn ($f) => $f->pagina?->titulo ?? 'p.'.$f->pagina_id)->filter()->join(' · ') }}
                </div>
            @endif
        </div>
        <div class="footer-right">Pág. <span class="pagenum"></span></div>
    </div>
</div>

{{-- ── Conteúdo ───────────────────────────────────────────────────────────── --}}

{{-- ╔══════════════════════════╗ --}}
{{-- ║  Prova em Branco (E2)   ║ --}}
{{-- ╚══════════════════════════╝ --}}
@if(in_array('prova_branca', $secoes))
    <div class="section-title">Prova em Branco</div>

    @foreach($questoesME as $i => $q)
        <div class="question">
            <div class="question-num">{{ $i + 1 }}. Múltipla Escolha</div>
            @if($q['contexto'] ?? null)
                <div class="question-context">{{ $q['contexto'] }}</div>
            @endif
            <div class="question-text">{{ $q['enunciado'] }}</div>
            @foreach(['a', 'b', 'c', 'd', 'e'] as $l)
                @if($q['alternativas'][$l] ?? null)
                    <div class="option">{{ strtoupper($l) }}) {{ $q['alternativas'][$l] }}</div>
                @endif
            @endforeach
        </div>
    @endforeach

    @foreach($questoesDis as $i => $q)
        @php $num = count($questoesME) + $i + 1; @endphp
        <div class="question">
            <div class="question-num">{{ $num }}. Dissertativa</div>
            <div class="question-text">{{ $q['enunciado'] }}</div>
            @if($q['rubrica'] ?? null)
                <div class="rubrica-box">
                    <strong>Critérios:</strong>
                    @foreach($q['rubrica'] as $c) {{ $c['criterio'] }} (peso {{ $c['peso'] }}); @endforeach
                </div>
            @endif
            <div class="blank-area"></div>
        </div>
    @endforeach
@endif

{{-- ╔════════════════════════════════╗ --}}
{{-- ║  Gabarito Comentado (E3)       ║ --}}
{{-- ╚════════════════════════════════╝ --}}
@if(in_array('gabarito', $secoes))
    @php $firstSection = ! in_array('prova_branca', $secoes); @endphp
    <div class="section-title{{ $firstSection ? '' : ' section-break' }}">Gabarito Comentado</div>

    @foreach($questoesME as $i => $q)
        <div class="question">
            <div class="question-num">{{ $i + 1 }}. ME — Gabarito: {{ strtoupper($q['correta']) }}</div>
            @if($q['contexto'] ?? null)
                <div class="question-context">{{ $q['contexto'] }}</div>
            @endif
            <div class="question-text">{{ $q['enunciado'] }}</div>
            @foreach(['a', 'b', 'c', 'd', 'e'] as $l)
                @if($q['alternativas'][$l] ?? null)
                    <div class="option {{ $l === $q['correta'] ? 'correct' : '' }}">
                        {{ strtoupper($l) }}) {{ $q['alternativas'][$l] }}
                    </div>
                @endif
            @endforeach
            @if($q['comentario_gabarito'] ?? null)
                <div class="comment-box">
                    @foreach($q['comentario_gabarito'] as $l => $com)
                        <div class="comment-row"><strong>{{ strtoupper($l) }}):</strong> {{ $com }}</div>
                    @endforeach
                </div>
            @endif
            @php $fonteIds = collect($q['fontes'] ?? [])->pluck('pagina_id')->unique(); @endphp
            @if($fonteIds->isNotEmpty())
                <div class="question-sources">
                    Fontes: {{ $fonteIds->map(fn ($pid) => $fontesPaginas[$pid]?->pagina?->titulo ?? 'p.'.$pid)->filter()->join(' · ') }}
                </div>
            @endif
        </div>
    @endforeach

    @foreach($questoesDis as $i => $q)
        @php $num = count($questoesME) + $i + 1; @endphp
        <div class="question">
            <div class="question-num">{{ $num }}. Dissertativa</div>
            <div class="question-text">{{ $q['enunciado'] }}</div>
            @if($q['gabarito_referencia'] ?? null)
                <div class="comment-box">{{ $q['gabarito_referencia'] }}</div>
            @endif
            @if($q['rubrica'] ?? null)
                <div class="rubrica-box">
                    <strong>Critérios:</strong>
                    @foreach($q['rubrica'] as $c) {{ $c['criterio'] }} (peso {{ $c['peso'] }}); @endforeach
                </div>
            @endif
            @php $fonteIds = collect($q['fontes'] ?? [])->pluck('pagina_id')->unique(); @endphp
            @if($fonteIds->isNotEmpty())
                <div class="question-sources">
                    Fontes: {{ $fonteIds->map(fn ($pid) => $fontesPaginas[$pid]?->pagina?->titulo ?? 'p.'.$pid)->filter()->join(' · ') }}
                </div>
            @endif
        </div>
    @endforeach
@endif

{{-- ╔════════════════════════════════════════╗ --}}
{{-- ║  Minhas Respostas + Resultado (E4)     ║ --}}
{{-- ╚════════════════════════════════════════╝ --}}
@if(in_array('respostas', $secoes) && $resposta)
    @php
        $hasPrev = in_array('prova_branca', $secoes) || in_array('gabarito', $secoes);
    @endphp
    <div class="section-title{{ $hasPrev ? ' section-break' : '' }}">Minhas Respostas e Resultado</div>

    @php
        $pontosME  = $resposta->acertos;
        $totalME   = $resposta->total;
        $notasDis  = $resposta->notas_dissertativas ?? [];
        $pontosDis = collect($notasDis)->sum('nota_total');
        $total     = $totalME + count($notasDis);
        $pontos    = $pontosME + $pontosDis;
        $pct       = $total > 0 ? round($pontos / $total * 100) : 0;
    @endphp

    <div class="score-box">
        <strong>Resultado:</strong>
        {{ number_format($pontos, 1) }} / {{ $total }} pontos — {{ $pct }}%
        @if($resposta->tempo_realizado_segundos)
            &nbsp;·&nbsp; Tempo: {{ gmdate('i:s', $resposta->tempo_realizado_segundos) }}
        @endif
    </div>

    @foreach($questoesME as $i => $q)
        @php
            $dada    = ($resposta->respostas ?? [])[(string) $i] ?? null;
            $acertou = $dada === $q['correta'];
        @endphp
        <div class="question">
            <div class="question-num">{{ $i + 1 }}. ME — {{ $acertou ? '✓ Correta' : '✗ Incorreta' }}</div>
            <div class="question-text">{{ $q['enunciado'] }}</div>
            @foreach(['a', 'b', 'c', 'd', 'e'] as $l)
                @if($q['alternativas'][$l] ?? null)
                    @php
                        $cls = '';
                        if ($l === $q['correta']) $cls = 'correct';
                        elseif ($l === $dada && !$acertou) $cls = 'wrong';
                    @endphp
                    <div class="option {{ $cls }}">{{ strtoupper($l) }}) {{ $q['alternativas'][$l] }}</div>
                @endif
            @endforeach
        </div>
    @endforeach

    @foreach($questoesDis as $i => $q)
        @php
            $num   = count($questoesME) + $i + 1;
            $nDis  = $notasDis[$i] ?? null;
            $texto = ($resposta->respostas_dissertativas ?? [])[(string) $i] ?? '';
        @endphp
        <div class="question">
            <div class="question-num">
                {{ $num }}. Dissertativa
                @if($nDis) &mdash; {{ number_format($nDis['nota_total'] ?? 0, 2) }} / 1,00 @endif
            </div>
            <div class="question-text">{{ $q['enunciado'] }}</div>
            @if($texto)
                <div class="comment-box" style="border-left-color: #9ca3af; background: #f9fafb;">
                    <strong>Minha resposta:</strong> {{ $texto }}
                </div>
            @endif
            @if($nDis)
                <div class="comment-box">
                    @foreach($nDis['notas'] ?? [] as $nc)
                        <div class="comment-row">
                            <strong>{{ $nc['criterio'] }}:</strong>
                            {{ number_format($nc['nota'] ?? 0, 2) }} — {{ $nc['feedback'] ?? '' }}
                        </div>
                    @endforeach
                    @if($nDis['feedback_geral'] ?? null)
                        <div style="margin-top:3px;font-style:italic">{{ $nDis['feedback_geral'] }}</div>
                    @endif
                </div>
            @endif
        </div>
    @endforeach
@endif

</body>
</html>
