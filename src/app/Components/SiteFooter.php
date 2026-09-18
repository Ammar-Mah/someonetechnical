<?php

/**
 * The foot of the page: every item PRODUCT.md §10 lists, and its closing line.
 *
 * The section and intake links read SiteHeader's constants, the one place
 * those targets are defined, and the section ones carry its HOME_HREF so they
 * work from the intake too (#42). Privacy, Terms and Contact are the views #17
 * adds to $views in public/index.php.
 *
 * Two short lists rather than one row: the service links, then the rest. Each
 * is read top to bottom, left list first, which is also the Tab order.
 */
class SiteFooter extends Component
{
    public $name = "";

    protected string $template = '
        <footer class="site-footer">
            <div class="site-footer-inner">
                <div>
                    <p class="site-brand">{{$name}}</p>
                    <p class="site-footer-domain">someonetechnical.com</p>
                </div>
                <nav class="site-footer-nav" aria-label="Footer">
                    <ul>
                        <li><a href="' . SiteHeader::HOME_HREF . '#' . SiteHeader::HOW_IT_WORKS . '">How it works</a></li>
                        <li><a href="' . SiteHeader::HOME_HREF . '#' . SiteHeader::WHAT_WE_HELP_WITH . '">What we help with</a></li>
                        <li><a href="' . SiteHeader::START_HREF . '">Book a session</a></li>
                    </ul>
                    <ul>
                        <li><a href="?page=privacy">Privacy</a></li>
                        <li><a href="?page=terms">Terms</a></li>
                        <li><a href="?page=contact">Contact</a></li>
                    </ul>
                </nav>
                <p class="site-footer-line">Human technical help for people building with AI.</p>
            </div>
        </footer>';

    public function mount()
    {
        $this->name = Logo::appName();
    }
}
