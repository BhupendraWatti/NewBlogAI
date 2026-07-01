                <!-- MEDIA STUDIO WORKSPACE -->
                <div id="node-media" class="workspace-pane space-y-6 hidden">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="font-display font-bold text-2xl">Media Studio</h2>
                            <p class="text-xs text-muted">Manage featured images, AI-generated visuals, logos, brand assets, and reusable templates across all connected sites.</p>
                        </div>
                        <button onclick="document.getElementById('media-upload-input').click()" class="bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5 cyber-glow-emerald">
                            <span class="material-symbols-outlined text-sm font-bold">upload</span> Upload Asset
                        </button>
                        <input type="file" id="media-upload-input" class="hidden" accept="image/*" multiple/>
                    </div>

                    <!-- Category Tabs -->
                    <div class="flex items-center gap-1 p-1 bg-surface border border-border rounded-xl w-fit" id="media-category-tabs">
                        <button onclick="switchMediaCategory('featured')" data-cat="featured" class="media-cat-btn px-3 py-1.5 rounded-lg text-xs font-medium text-accent bg-white/5 transition">Featured Images</button>
                        <button onclick="switchMediaCategory('ai')" data-cat="ai" class="media-cat-btn px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:text-text hover:bg-white/5 transition">AI Images</button>
                        <button onclick="switchMediaCategory('logos')" data-cat="logos" class="media-cat-btn px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:text-text hover:bg-white/5 transition">Logos</button>
                        <button onclick="switchMediaCategory('brand')" data-cat="brand" class="media-cat-btn px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:text-text hover:bg-white/5 transition">Brand Assets</button>
                        <button onclick="switchMediaCategory('templates')" data-cat="templates" class="media-cat-btn px-3 py-1.5 rounded-lg text-xs font-medium text-muted hover:text-text hover:bg-white/5 transition">Templates</button>
                    </div>

                    <!-- Category: Featured Images -->
                    <div id="media-cat-featured" class="media-category-pane">
                        <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                            <div class="flex items-center justify-between p-4 border-b border-border">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Featured Images</h4>
                                <div class="relative w-52">
                                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-muted text-sm">search</span>
                                    <input class="w-full bg-background border border-border rounded-xl py-1.5 pl-9 pr-4 text-xs font-mono text-text placeholder-muted focus:outline-none focus:border-accent" placeholder="Search assets..." type="text"/>
                                </div>
                            </div>
                            <!-- Empty State -->
                            <div class="flex flex-col items-center justify-center py-20 text-center" id="featured-empty-state">
                                <span class="material-symbols-outlined text-4xl text-muted mb-3">image</span>
                                <h3 class="font-display font-bold text-base mb-1">No Featured Images</h3>
                                <p class="text-xs text-muted max-w-xs">Upload featured images to use as article thumbnails and site covers across connected WordPress sites.</p>
                                <button onclick="document.getElementById('media-upload-input').click()" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">upload</span> Upload Image
                                </button>
                            </div>
                            <!-- TODO: Populate grid from GET /api/v1/media?category=featured -->
                        </div>
                    </div>

                    <!-- Category: AI Images -->
                    <div id="media-cat-ai" class="media-category-pane hidden">
                        <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                            <div class="p-4 border-b border-border">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">AI Images</h4>
                            </div>
                            <!-- Empty State -->
                            <div class="flex flex-col items-center justify-center py-20 text-center">
                                <span class="material-symbols-outlined text-4xl text-muted mb-3">auto_awesome</span>
                                <h3 class="font-display font-bold text-base mb-1">No AI Images Generated</h3>
                                <p class="text-xs text-muted max-w-xs">AI-generated images will appear here after content generation runs that include image creation are executed.</p>
                                <p class="text-[10px] font-mono text-muted mt-3"><!-- TODO: GET /api/v1/media?category=ai_generated --></p>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Logos -->
                    <div id="media-cat-logos" class="media-category-pane hidden">
                        <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                            <div class="p-4 border-b border-border">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Logos</h4>
                            </div>
                            <!-- Empty State -->
                            <div class="flex flex-col items-center justify-center py-20 text-center">
                                <span class="material-symbols-outlined text-4xl text-muted mb-3">logo_dev</span>
                                <h3 class="font-display font-bold text-base mb-1">No Logos Uploaded</h3>
                                <p class="text-xs text-muted max-w-xs">Upload site and brand logos to use across WordPress themes and article metadata.</p>
                                <button onclick="document.getElementById('media-upload-input').click()" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">upload</span> Upload Logo
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Brand Assets -->
                    <div id="media-cat-brand" class="media-category-pane hidden">
                        <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                            <div class="p-4 border-b border-border">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Brand Assets</h4>
                            </div>
                            <!-- Empty State -->
                            <div class="flex flex-col items-center justify-center py-20 text-center">
                                <span class="material-symbols-outlined text-4xl text-muted mb-3">style</span>
                                <h3 class="font-display font-bold text-base mb-1">No Brand Assets</h3>
                                <p class="text-xs text-muted max-w-xs">Upload brand kits, banners, colour palettes, and visual identity assets for your connected sites.</p>
                                <button onclick="document.getElementById('media-upload-input').click()" class="mt-4 bg-accent hover:bg-accent/80 text-background font-medium text-xs px-4 py-2 rounded-xl transition flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-sm">upload</span> Upload Asset
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Category: Templates -->
                    <div id="media-cat-templates" class="media-category-pane hidden">
                        <div class="glass-surface rounded-2xl overflow-hidden border border-border">
                            <div class="p-4 border-b border-border">
                                <h4 class="text-xs font-mono uppercase tracking-widest text-muted">Templates</h4>
                            </div>
                            <!-- Empty State -->
                            <div class="flex flex-col items-center justify-center py-20 text-center">
                                <span class="material-symbols-outlined text-4xl text-muted mb-3">dashboard_customize</span>
                                <h3 class="font-display font-bold text-base mb-1">No Image Templates</h3>
                                <p class="text-xs text-muted max-w-xs">Define reusable image layout templates for article featured images, social banners, and thumbnails.</p>
                                <p class="text-[10px] font-mono text-muted mt-3"><!-- TODO: GET /api/v1/media?category=templates --></p>
                            </div>
                        </div>
                    </div>

                </div>
