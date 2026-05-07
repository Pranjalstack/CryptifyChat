<div class="theme-toggle-float" id="themeSwitcher" title="Toggle System Protocol">
    <i class="fa-solid fa-moon" id="themeIcon"></i>
</div>

<div id="cursorNeon"></div>

<style>
    /* 1. Global Cursor Hide */
    html, body, a, button, input, textarea, select {
        cursor: none !important;
    }

    /* 2. Floating Toggle - Left Side */
    .theme-toggle-float {
        position: fixed;
        bottom: 30px;
        left: 30px;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: #121212;
        color: #d4af37;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 2000000; 
        border: 1px solid #d4af37;
        box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        pointer-events: auto !important;
    }

    /* 3. Neon Cursor */
    #cursorNeon {
        width: 12px;
        height: 12px;
        background-color: #1bd1a5; 
        position: fixed;
        top: 0;
        left: 0;
        transform: translate(-50%, -50%);
        border-radius: 50%;
        z-index: 2000001; 
        pointer-events: none;
        box-shadow: 0 0 10px #1bd1a5, 0 0 20px #1bd1a5;
    }

    /* Trail Particles */
    .trail-particle {
        position: fixed;
        width: 4px;
        height: 4px;
        background: #1bd1a5;
        border-radius: 50%;
        pointer-events: none;
        z-index: 1999999;
    }

    /* Cursor Hover State */
    .cursor-hovering {
        transform: translate(-50%, -50%) scale(2) !important;
        background-color: #d4af37 !important;
        box-shadow: 0 0 15px #d4af37 !important;
    }

    /* 4. Visibility Fixes for Sidebar Text */
    /* Light Mode (Default) - Ensure text is dark enough to see against light background */
    body:not(.dark-theme) .left-sidebar-name, 
    body:not(.dark-theme) .verified-node-text,
    body:not(.dark-theme) h5, 
    body:not(.dark-theme) p {
        color: #1a1a1a !important;
    }

    /* Dark Mode - Ensure text is light enough to see against dark background */
    body.dark-theme .left-sidebar-name, 
    body.dark-theme .verified-node-text,
    body.dark-theme h5, 
    body.dark-theme p {
        color: #ffffff !important;
    }
</style>

<script>
    // THEME LOGIC - Runs immediately
    const toggleBtn = document.getElementById('themeSwitcher');
    const toggleIcon = document.getElementById('themeIcon');
    const htmlNode = document.documentElement;
    const bodyNode = document.body;

    // Apply saved theme immediately
    if (localStorage.getItem('vault-theme') === 'dark') {
        htmlNode.setAttribute('data-theme', 'dark');
        document.body.classList.add('dark-theme'); // Backup class-based toggle
        if (toggleIcon) toggleIcon.classList.replace('fa-moon', 'fa-sun');
    }

    // Toggle function - Fixed to target all possible CSS entry points
    function handleThemeToggle() {
        const currentTheme = htmlNode.getAttribute('data-theme');
        if (currentTheme === 'dark') {
            htmlNode.removeAttribute('data-theme');
            document.body.classList.remove('dark-theme'); // Remove backup class
            localStorage.setItem('vault-theme', 'light');
            if (toggleIcon) toggleIcon.classList.replace('fa-sun', 'fa-moon');
        } else {
            htmlNode.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-theme'); // Add backup class
            localStorage.setItem('vault-theme', 'dark');
            if (toggleIcon) toggleIcon.classList.replace('fa-moon', 'fa-sun');
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            handleThemeToggle();
        });
    }

    // CURSOR LOGIC
    const neonDot = document.getElementById('cursorNeon');

    document.addEventListener('mousemove', (e) => {
        if (neonDot) {
            neonDot.style.left = e.clientX + 'px';
            neonDot.style.top = e.clientY + 'px';
        }
        
        if (Math.random() > 0.15) {
            spawnParticle(e.clientX, e.clientY);
        }
    });

    // Cursor Hover detection
    document.addEventListener('mouseover', (e) => {
        if (e.target.closest('a, button, .theme-toggle-float, input, textarea')) {
            if(neonDot) neonDot.classList.add('cursor-hovering');
        }
    });
    document.addEventListener('mouseout', (e) => {
        if (e.target.closest('a, button, .theme-toggle-float, input, textarea')) {
            if(neonDot) neonDot.classList.remove('cursor-hovering');
        }
    });

    function spawnParticle(x, y) {
        const p = document.createElement('div');
        p.className = 'trail-particle';
        p.style.left = x + 'px';
        p.style.top = y + 'px';
        document.body.appendChild(p);

        p.animate([
            { transform: 'scale(1)', opacity: 0.8 },
            { transform: `translate(${(Math.random()-0.5)*30}px, ${(Math.random()-0.5)*30}px) scale(0)`, opacity: 0 }
        ], { duration: 600 }).onfinish = () => p.remove();
    }
</script>