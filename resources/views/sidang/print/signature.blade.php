<div class="signature">
    @if ($signatureDate ?? true)<p>{{ $fields['placeDate'] }}</p>@endif
    @if ($signatureCaption ?? null)<p>{{ $signatureCaption }}</p>@endif
    <p>{{ $signatureRole ?? '' }}</p>
    <p class="signature-space">(<span @class(['blank' => empty($signatureName)])>{{ $signatureName ?? '' }}</span>)</p>
    @if ($signatureNik ?? null)<p>Nik. {{ $signatureNik }}</p>@endif
</div>
