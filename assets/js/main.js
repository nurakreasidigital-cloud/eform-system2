// ============================================================
// MAIN.JS - GLOBAL FUNCTIONS
// ============================================================

// ===== SIDEBAR TOGGLE UNTUK MOBILE =====
function toggleSidebar() {
    var sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('open');
    }
    var overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.classList.toggle('show');
    }
}

// ===== CLOSE SIDEBAR =====
function closeSidebar() {
    var sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.remove('open');
    }
    var overlay = document.querySelector('.sidebar-overlay');
    if (overlay) {
        overlay.classList.remove('show');
    }
}

// ===== INIT =====
document.addEventListener('DOMContentLoaded', function() {
    // Tambahkan overlay
    var overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    
    overlay.addEventListener('click', function() {
        closeSidebar();
    });

    // Tutup sidebar jika resize ke desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
        }
    });

    // Tutup sidebar jika klik di luar
    document.addEventListener('click', function(e) {
        var sidebar = document.querySelector('.sidebar');
        var hamburger = document.querySelector('.hamburger');
        if (sidebar && !sidebar.contains(e.target) && !hamburger?.contains(e.target)) {
            closeSidebar();
        }
    });

    // Auto close alerts
    document.querySelectorAll('.alert').forEach(function(el) {
        setTimeout(function() {
            el.style.opacity = '0';
            setTimeout(function() { el.style.display = 'none'; }, 300);
        }, 4000);
    });
});

// ============================================================
// NOTIFICATION TOGGLE (untuk admin)
// ============================================================
function toggleNotif() {
    var dropdown = document.getElementById('notifDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Tutup notif jika klik di luar
document.addEventListener('click', function(e) {
    var bell = document.querySelector('.notif-bell');
    var dropdown = document.getElementById('notifDropdown');
    if (bell && dropdown && !bell.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// ===== NOTIFICATION BADGE =====
function updateNotifBadge() {
    fetch('api/get_notif_count.php')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                if (data.count > 0) {
                    badge.style.display = 'inline';
                    badge.textContent = data.count;
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(function() {});
}

// Update badge setiap 30 detik
setInterval(updateNotifBadge, 30000);

// Update saat halaman dimuat
document.addEventListener('DOMContentLoaded', function() {
    updateNotifBadge();
});
