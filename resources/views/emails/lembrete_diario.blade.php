<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1a1a1a; max-width: 520px; margin: 0 auto; padding: 24px;">

<h2 style="color: #4f46e5;">📚 StudyWiki — Lembrete de estudos</h2>

@if($flashcardsPendentes > 0)
<p>Você tem <strong>{{ $flashcardsPendentes }} flashcard{{ $flashcardsPendentes > 1 ? 's' : '' }}</strong> para revisar hoje.</p>
@endif

@if($streakAtual > 0)
<p>Seu streak atual é de <strong>{{ $streakAtual }} dia{{ $streakAtual > 1 ? 's' : '' }}</strong>. Estude hoje para não perder!</p>
@endif

<p style="margin-top: 24px;">
    <a href="{{ url('/trilha') }}" style="background: #4f46e5; color: #fff; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
        Ver Trilha de estudos
    </a>
</p>

<p style="color: #6b7280; font-size: 12px; margin-top: 32px;">
    Para desativar os lembretes, acesse as configurações do StudyWiki.
</p>

</body>
</html>
