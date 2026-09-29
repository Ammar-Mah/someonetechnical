@extend('app')

@section('title')Privacy · Someone Technical@endsection
@section('description')What Someone Technical collects when you ask for help, why, how long it is kept, who processes it, and how to see, correct or delete it.@endsection
@section('path')?page=privacy@endsection

@section('content')

{{ SiteHeader::make('site-header') }}

{{-- The owner asked for common-standard text (#17, 2026-09-29). "What we
     collect" names every column IntakeRequest stores; tests/cases/site.php
     holds the phrase for each and fails when a column has none. --}}
<main id="main" class="site-main legal">
    <article class="legal-inner">
        <h1>Privacy</h1>
        <p class="legal-updated">Last updated 29 September 2026</p>

        <p>This notice explains what Someone Technical collects when you use someonetechnical.com, why, how long it is kept and what you can ask us to do with it. We collect as little as we can, and we never sell it.</p>

        <h2>Who is responsible</h2>
        <p>Someone Technical is responsible for the personal data described here. To reach us about it, use the <a href="?page=contact">Contact</a> page.</p>

        <h2>What we collect</h2>
        <p>When you send a request from the <a href="<?= e(SiteHeader::START_HREF) ?>">intake</a>, we store:</p>
        <ul>
            <li>what you are building;</li>
            <li>which AI building tool you use;</li>
            <li>what you are stuck on;</li>
            <li>whether the project is already live;</li>
            <li>whether you want guidance, hands-on help, or are unsure;</li>
            <li>your name;</li>
            <li>your email address;</li>
            <li>your preferred session time;</li>
            <li>when the request was sent and when it was last changed;</li>
            <li>when the request was deleted, if it was.</li>
        </ul>
        <p>Every answer except your name and email address is optional, and "I don't know" is always fine. Please do not send passwords, keys or other credentials through the intake. We never ask for them there.</p>
        <p>When you visit, the server also sees your IP address and your browser's usual request details. They are used only to deliver the page, keep the site secure and stop abuse.</p>

        <h2>Why we use it</h2>
        <ul>
            <li>To reply to your request and arrange and run a session. That is a step you asked us to take, before any agreement.</li>
            <li>To keep the site secure and stop abuse, for example by limiting how many requests one address can send in an hour. That is our legitimate interest in running a safe service.</li>
            <li>To keep records the law requires, if you become a customer.</li>
        </ul>
        <p>We do not use your data for advertising, sell it, or use it to train AI models.</p>

        <h2>Cookies</h2>
        <p>The site sets one cookie, a session cookie that lets the intake form send safely. It is strictly necessary, holds no personal details and expires after 30 days. There are no analytics, advertising or tracking cookies, and the pages load nothing from other companies' servers.</p>

        <h2>Who else processes it</h2>
        <ul>
            <li>Our hosting provider stores the site, its database and its logs.</li>
            <li>Our email provider delivers the message that tells us about your request, and carries the emails we exchange with you.</li>
        </ul>
        <p>Both act only on our instructions. If a provider processes data outside your country, we rely on the safeguards the law requires, such as standard contractual clauses.</p>

        <h2>How long we keep it</h2>
        <ul>
            <li>A request is kept for 12 months after our last contact with you, then deleted, unless you become a customer and the law requires us to keep records for longer.</li>
            <li>Server logs, which include IP addresses, are kept for up to 90 days.</li>
            <li>The count used to limit requests from one address is kept for one hour, and the address itself is not stored with it.</li>
        </ul>

        <h2>Your rights</h2>
        <p>You can ask to see the data we hold about you, correct it, delete it, restrict or object to how we use it, or receive a copy you can take elsewhere. Ask through the <a href="?page=contact">Contact</a> page, giving the email address you used. We answer within one month. You can also complain to the data protection authority where you live.</p>

        <h2>Security</h2>
        <p>The site is served over an encrypted connection. Stored requests are not reachable from the web, and access to them is limited to the people who answer them. No system is perfectly secure, but we take reasonable care, and we will tell you if a breach puts your data at risk.</p>

        <h2>Children</h2>
        <p>The service is for adults. We do not knowingly collect data from anyone under 16.</p>

        <h2>Changes</h2>
        <p>If this notice changes, the date at the top changes with it. If a change affects requests you already sent, we will tell you.</p>
    </article>
</main>

{{ SiteFooter::make('site-footer') }}

@endsection
