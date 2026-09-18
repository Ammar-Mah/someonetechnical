@extend('app')

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The intake is the whole page. The shell's section links are written
     './#…', so from here they lead to the home page's sections — see
     ARCHITECTURE.md → Hazards and DECISIONS.md, 2026-09-18. --}}
<main id="main" class="site-main">
    {{ IntakeScreen::make('intake') }}
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
