@extend('app')

@section('title')Get someone technical · Someone Technical@endsection
@section('description')Tell us what you are building and where you are stuck, in plain words. Someone technical reads it and comes back to you with a time that works.@endsection
@section('path')?page=start@endsection

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
