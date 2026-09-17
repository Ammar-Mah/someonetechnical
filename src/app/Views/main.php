@extend('app')

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The sections render inside <main>, in PRODUCT.md order, the hero first.
     Their anchor ids are SiteHeader's constants — see ARCHITECTURE.md →
     Sections. --}}
<main id="main" class="site-main">
    {{ HeroSection::make('hero') }}
    {{ RecognitionSection::make('recognition') }}
    {{ HowItWorksSection::make(SiteHeader::HOW_IT_WORKS) }}
    {{ HelpTypesSection::make('help-types') }}
    {{ ContinuitySection::make('continuity') }}
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
