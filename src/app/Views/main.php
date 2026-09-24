@extend('app')

@section('title')Someone Technical@endsection
@section('description')One-to-one help from an experienced engineer when your AI-built app is stuck on deployment, domains, databases, sign-in, payments or security. You stay in control.@endsection
@section('path')@endsection

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The five sections render inside <main>, in PRODUCT.md order, the hero
     first. Their anchor ids are SiteHeader's constants — see ARCHITECTURE.md →
     Sections. --}}
<main id="main" class="site-main">
    {{ HeroSection::make('hero') }}
    {{ RecognitionSection::make('recognition') }}
    {{ HowItWorksSection::make(SiteHeader::HOW_IT_WORKS) }}
    {{ SupportAreasSection::make(SiteHeader::WHAT_WE_HELP_WITH) }}
    {{ FinalCtaSection::make('final-cta') }}
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
