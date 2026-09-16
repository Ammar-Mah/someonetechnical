@extend('app')

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The sections render inside <main>, in PRODUCT.md order. Their anchor ids
     are SiteHeader's constants — see ARCHITECTURE.md → Sections. The hero
     (#10) goes above RecognitionSection when it arrives. --}}
<main id="main" class="site-main">
    {{ RecognitionSection::make('recognition') }}
    {{ HowItWorksSection::make(SiteHeader::HOW_IT_WORKS) }}
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
