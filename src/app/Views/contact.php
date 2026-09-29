@extend('app')

@section('title')Contact · Someone Technical@endsection
@section('description')How to reach Someone Technical: ask for help through the intake, or ask about your data, a booking or these pages the same way.@endsection
@section('path')?page=contact@endsection

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The intake is the public contact method: the owner has not published an
     address, and the intake already reaches them (#17, DECISIONS 2026-09-29). --}}
<main id="main" class="site-main legal">
    <article class="legal-inner">
        <h1>Contact</h1>

        <p>The quickest way to reach someone technical is the intake. It takes a couple of minutes, and a real person reads every request.</p>
        <div class="legal-action">
            <a class="site-cta" href="<?= e(SiteHeader::START_HREF) ?>">Get someone technical</a>
        </div>

        <h2>Anything else</h2>
        <p>Use the same form for anything else: a question about a booking, these pages or your data. Say what it is about in "What are you currently stuck on?", and leave the other questions as "I don't know". We reply by email to the address you give.</p>

        <h2>About your data</h2>
        <p>To see, correct or delete what we hold about you, give the email address you used before, so we can match the request to you. We answer within one month. The <a href="?page=privacy">Privacy</a> notice explains what we keep and why.</p>
    </article>
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
