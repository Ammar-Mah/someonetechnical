@extend('app')

@section('content')

<div class="app-shell">

    {{ AppHeader::make('header')
         ->left(Logo::make())
         ->right(IconButton::make('theme-toggle')->set('moon')->tooltip('Toggle theme')
                   ->onClick('AppHandler.toggleTheme()')) }}

    <div class="app-main">

        <aside class="app-sidebar">
            {{ SideNav::make('nav') }}
        </aside>

        {{-- The screen the person was last on. Navigation replaces the inside
             of this element and nothing else on the page. --}}
        <main class="app-content" id="app-content">
            {{ AppHandler::renderScreen() }}
        </main>

    </div>

</div>

@endsection
