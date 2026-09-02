@include('partials.head')
<body class="font-sans antialiased overflow-hidden min-h-screen flex h-screen w-full select-none bg-background text-text">

    <button id="sidebar-overlay" type="button" onclick="closeMobileSidebar()" class="fixed inset-0 z-40 hidden bg-slate-950/60 backdrop-blur-sm md:hidden" aria-label="Close navigation menu"></button>

    @include('partials.sidebar')

    <!-- CORE WORKSPACE CONTAINER -->
    <main class="flex-1 flex flex-col overflow-hidden min-w-0 bg-background relative">

        @include('partials.header')

        @include('partials.workspace-tabs')

        <!-- MAIN VIEW WRAPPER -->
        <div class="flex-1 flex overflow-hidden relative">

            <!-- CENTRAL WORKSPACE SPACE -->
            <div class="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-6 space-y-6" id="workspace-content">

                @include('partials.workspaces.dashboard')
                @include('partials.workspaces.customers')
                @include('partials.workspaces.plans')
                @include('partials.workspaces.fleet')
                @include('partials.workspaces.sites')
                @include('partials.workspaces.prompts')
                @include('partials.workspaces.rules')
                @include('partials.workspaces.topics')
                @include('partials.workspaces.pipeline')
                @include('partials.workspaces.scheduler')
                @include('partials.workspaces.providers')
                @include('partials.workspaces.media')
                @include('partials.workspaces.seo')
                @include('partials.workspaces.analytics')
                @include('partials.workspaces.notifications')
                @include('partials.workspaces.roles')
                @include('partials.workspaces.billing')
                @include('partials.workspaces.settings')
                @include('partials.workspaces.master-settings')
                @include('partials.workspaces.audit')
                @include('partials.workspaces.design')
                @include('partials.workspaces.creation-wizard')

            </div>

            @include('partials.inspector-panel')

        </div>
    </main>

    @include('partials.scripts')
</body>
</html>
