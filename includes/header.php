<nav class="navbar">
    <div class="navbar-left">
        <button id="sidebarToggle" class="btn btn-outline" style="padding: 0.5rem 0.75rem;">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="navbar-center" style="flex: 1; max-width: 500px; margin: 0 2rem;">
        <div style="position: relative; display: flex; align-items: center;">
            <i class="fas fa-search" style="position: absolute; left: 1rem; color: var(--text-muted); pointer-events: none;"></i>
            <input type="text" placeholder="Cari No. Resi, Dokumen, atau Invoice..." 
                   style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.75rem; border-radius: 999px; border: 1px solid var(--border-color); background: var(--bg-main); outline: none; transition: var(--transition);"
                   onfocus="this.style.borderColor='var(--primary-light)'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'"
                   onblur="this.style.borderColor='var(--border-color)'; this.style.boxShadow='none'">
        </div>
    </div>

    <div class="navbar-right" style="display: flex; align-items: center; gap: 1rem;">
        <div style="position: relative;">
            <button class="btn btn-outline" style="border: none; padding: 0.5rem; color: var(--text-muted);">
                <i class="fas fa-bell"></i>
                <span style="position: absolute; top: 0; right: 0; width: 8px; height: 8px; background: var(--accent); border-radius: 50%; border: 2px solid white;"></span>
            </button>
        </div>
        
        <div style="height: 32px; width: 1px; background: var(--border-color);"></div>

        <div style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer;">
            <div style="text-align: right; display: none; @media(min-width: 640px) { display: block; }">
                <div style="font-weight: 600; font-size: 0.875rem;">Aditya Pratama</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Admin Logistik</div>
            </div>
            <img src="https://ui-avatars.com/api/?name=Aditya+Pratama&background=1e3a8a&color=fff" 
                 alt="User Profile" 
                 style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color);">
        </div>
    </div>
</nav>
