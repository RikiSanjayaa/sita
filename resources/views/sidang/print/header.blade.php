<header class="letterhead">
    <img src="{{ asset('images/ubg-letterhead.png') }}" alt="Logo {{ $fields['letterheadInstitution'] }}">
    <div>
        <strong class="institution">{{ $fields['letterheadInstitution'] }}</strong>
        @if ($fields['letterheadFaculty'] !== '')<strong>{{ $fields['letterheadFaculty'] }}</strong>@endif
        <strong>{{ $fields['letterheadProgram'] }}</strong>
        <div class="address">{{ $fields['letterheadAddress'] }}</div>
        <div class="contact">{{ $fields['letterheadContact'] }}</div>
        <div class="address">{{ $fields['letterheadWebsite'] }}</div>
    </div>
</header>
