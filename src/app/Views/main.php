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
    {{ SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH) }}
    {{ PositioningSection::make('positioning') }}
    {{ HelpTypesSection::make('help-types') }}
    {{ ContinuitySection::make('continuity') }}
    {{ TrustSection::make('trust') }}
    {{ FinalCtaSection::make('final-cta') }}
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
