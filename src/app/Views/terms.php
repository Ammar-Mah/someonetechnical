@extend('app')

@section('title')Terms · Someone Technical@endsection
@section('description')The terms for using someonetechnical.com and for sessions with Someone Technical: what we do, what stays yours, fees, cancellations and liability.@endsection
@section('path')?page=terms@endsection

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The owner asked for common-standard text (#17, 2026-09-29). No price is
     named: PRODUCT.md rules out invented ones, so fees are agreed per session. --}}
<main id="main" class="site-main legal">
    <article class="legal-inner">
        <h1>Terms</h1>
        <p class="legal-updated">Last updated 29 September 2026</p>

        <p>These terms cover your use of someonetechnical.com and any session you book with Someone Technical ("we", "us"). By sending a request or joining a session, you agree to them.</p>

        <h2>What we do</h2>
        <p>We give one-to-one technical help, from an experienced engineer, to people building software with AI tools. We help you understand a problem, explain what is happening, and work toward a fix or a clear next step. We are not a development agency, and we do not take over your project.</p>

        <h2>Requests and sessions</h2>
        <p>Sending a request through the intake does not create a booking. We reply to agree a time, the kind of help and the fee. A session is booked once you confirm those details.</p>

        <h2>Fees and payment</h2>
        <p>The fee for a session, or for any further work, is agreed with you before it starts. You will never be charged for something you did not agree to first.</p>

        <h2>Cancelling and rescheduling</h2>
        <p>You can cancel or move a session free of charge up to 24 hours before it starts. If we have to cancel, we will offer another time or refund anything you paid for that session.</p>

        <h2>Your project stays yours</h2>
        <ul>
            <li>You own your project, its code, its data and its accounts. We claim no rights in them.</li>
            <li>You decide what changes are made. We explain what we suggest before we do it.</li>
            <li>Keep a current backup before a session that changes your project.</li>
        </ul>

        <h2>Access and credentials</h2>
        <p>If you choose to give us access to a system during a session, give only what the task needs, and change or revoke it afterwards. Never send passwords or keys through the intake form. We use any access only for the work you asked for, and we keep what we see confidential.</p>

        <h2>No guarantee of a fix</h2>
        <p>We work with care and skill, but not every problem can be solved in one session, and some depend on services outside our control. We do not promise a particular result, and we do not guarantee that a system is secure.</p>

        <h2>Liability</h2>
        <p>Nothing in these terms limits liability that the law does not allow to be limited, including for death or personal injury caused by negligence, or for fraud. Otherwise, we are not liable for indirect or consequential loss, such as lost profits or lost data, and our total liability for a session is limited to the fee you paid for it.</p>

        <h2>Using the website</h2>
        <p>Do not misuse the site: no automated or bulk requests, no attempts to break or probe its security, and nothing unlawful. The site's design, text and drawings belong to Someone Technical.</p>

        <h2>Your personal data</h2>
        <p>How we handle your data is described in the <a href="?page=privacy">Privacy</a> notice.</p>

        <h2>Changes to these terms</h2>
        <p>We may update these terms. The version in force when you book a session applies to that session.</p>

        <h2>Questions</h2>
        <p>Ask us anything about these terms through the <a href="?page=contact">Contact</a> page.</p>
    </article>
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
