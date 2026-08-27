<aside class="sidebar">
    <div class="brand">
        <span class="logo" aria-hidden="true"></span>
        <span class="brandtx"><b>LEA CRM</b><span>Terrassendach</span></span>
    </div>
    <nav class="nav">
        @include('partials.nav-links')
    </nav>
    <div class="sidebar-foot">
        <div class="ucard">
            <span class="uava">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
            <span class="uinfo">
                <b>{{ auth()->user()->name }}</b>
                <span>{{ auth()->user()->role->label() }}</span>
            </span>
        </div>
    </div>
</aside>
