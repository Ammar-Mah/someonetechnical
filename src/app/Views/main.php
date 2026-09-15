@extend('app')

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The sections render inside <main>, in PRODUCT.md order. Their anchor ids
     are SiteHeader's constants — see ARCHITECTURE.md → Sections. --}}
<main id="main" class="site-main"></main>

{{ SiteFooter::make('site-footer') }}

@endsection
