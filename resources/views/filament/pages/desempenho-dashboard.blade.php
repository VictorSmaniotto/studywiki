<x-filament-panels::page>
    @php $disciplinas = $this->getDadosPorDisciplina(); @endphp

    <x-filament::section>
        <x-slot name="heading">Por Disciplina</x-slot>
        <x-slot name="description">Custo em tokens e desempenho em simulados, agrupados por disciplina.</x-slot>

        @if(empty($disciplinas))
            <p style="font-size: 0.875rem; color: #9ca3af; font-style: italic;">Nenhuma geração registrada ainda.</p>
        @else
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid #e5e7eb;">
                            <th style="text-align: left; padding: 0.625rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Disciplina</th>
                            <th style="text-align: right; padding: 0.625rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Tokens</th>
                            <th style="text-align: center; padding: 0.625rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Resumos</th>
                            <th style="text-align: center; padding: 0.625rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Flashcards</th>
                            <th style="text-align: center; padding: 0.625rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Simulados</th>
                            <th style="text-align: right; padding: 0.625rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Rejeição</th>
                            <th style="text-align: right; padding: 0.625rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">Desempenho</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($disciplinas as $d)
                            <tr style="border-bottom: 1px solid #f3f4f6;">
                                <td style="padding: 0.75rem; font-weight: 500; color: #111827;">
                                    {{ $d['disciplina'] }}
                                </td>
                                <td style="padding: 0.75rem; text-align: right; font-family: monospace; color: #374151;">
                                    {{ number_format($d['total_tokens']) }}
                                </td>
                                <td style="padding: 0.75rem; text-align: center; color: #374151;">
                                    {{ $d['resumos'] }}
                                </td>
                                <td style="padding: 0.75rem; text-align: center; color: #374151;">
                                    {{ $d['flashcards'] }}
                                </td>
                                <td style="padding: 0.75rem; text-align: center; color: #374151;">
                                    {{ $d['simulados'] }}
                                </td>
                                <td style="padding: 0.75rem; text-align: right;">
                                    @php $corRejeicao = $d['taxa_rejeicao'] > 30 ? '#dc2626' : '#16a34a'; @endphp
                                    <span style="display: inline-flex; align-items: center; border-radius: 9999px; padding: 0.125rem 0.625rem; font-size: 0.75rem; font-weight: 500; background-color: {{ $d['taxa_rejeicao'] > 30 ? '#fef2f2' : '#f0fdf4' }}; color: {{ $corRejeicao }};">
                                        {{ $d['taxa_rejeicao'] }}%
                                    </span>
                                </td>
                                <td style="padding: 0.75rem; text-align: right;">
                                    @if($d['media_acertos_pct'] !== null)
                                        @php $corDesempenho = $d['media_acertos_pct'] >= 60 ? '#16a34a' : '#d97706'; @endphp
                                        <span style="font-weight: 600; color: {{ $corDesempenho }};">
                                            {{ $d['media_acertos_pct'] }}%
                                        </span>
                                        <span style="font-size: 0.75rem; color: #9ca3af; margin-left: 0.25rem;">
                                            ({{ $d['simulados_respondidos'] }})
                                        </span>
                                    @else
                                        <span style="color: #9ca3af;">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
