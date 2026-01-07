    </div>
</main>

<!-- Employee Zone Footer -->
<footer class="employee-footer">
    <div class="container-fluid">
        <div class="footer-content">
            <div class="footer-left">
                <span class="footer-text">
                    <?php echo $langs->trans('ZonaEmpleadoArea'); ?> - <?php echo $mysoc->name; ?>
                </span>
                <?php if (!empty($conf->global->MAIN_INFO_SOCIETE_WEB)) { ?>
                    <a href="<?php echo $conf->global->MAIN_INFO_SOCIETE_WEB; ?>" class="footer-link" target="_blank">
                        <i class="fas fa-globe"></i> <?php echo $langs->trans('Website'); ?>
                    </a>
                <?php } ?>
            </div>
            
            <div class="footer-right">
                <span class="footer-version">
                    v<?php echo DOL_VERSION; ?>
                </span>
                <?php if (!empty($conf->global->MAIN_FEATURES_LEVEL)) { ?>
                    <span class="footer-debug">
                        Debug: <?php echo $conf->global->MAIN_FEATURES_LEVEL; ?>
                    </span>
                <?php } ?>
            </div>
        </div>
    </div>
</footer>

<!-- JavaScript -->
<script src="<?php echo DOL_URL_ROOT; ?>/core/js/lib_foot.js.php?lang=<?php echo $langs->defaultlang; ?>"></script>

<!-- Employee Zone specific JavaScript -->
<script>
// Initialize ZonaEmpleado after all scripts are loaded
// This ensures that menus and UI work correctly even when other modules (like ZonaJob) load additional JS
document.addEventListener('DOMContentLoaded', function() {
    // Call ZonaEmpleado.init() if available to initialize menus, dropdowns, etc.
    if (typeof ZonaEmpleado !== 'undefined' && typeof ZonaEmpleado.init === 'function') {
        ZonaEmpleado.init();
    }
});

// Mobile menu toggle
document.addEventListener('DOMContentLoaded', function() {
    const mobileToggle = document.querySelector('.mobile-menu-toggle');
    const mobileNav = document.querySelector('.mobile-nav');
    
    if (mobileToggle && mobileNav) {
        mobileToggle.addEventListener('click', function() {
            mobileNav.classList.toggle('show');
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
        });
    }
    
    // User menu dropdown
    const userMenuBtn = document.querySelector('.user-menu-btn');
    const userDropdown = document.querySelector('.user-menu .dropdown-menu');
    
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            userDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });
    }
    
    // Dashboard cards animation
    const cards = document.querySelectorAll('.dashboard-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = (index * 0.1) + 's';
        card.classList.add('fade-in');
    });
});

// Quick search functionality (if implemented in the future)
function quickSearch(query) {
    // Placeholder for quick search functionality
    console.log('Searching for:', query);
}

// Notification system (placeholder)
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = 'employee-notification employee-notification-' + type;
    notification.innerHTML = '<i class="fas fa-info-circle"></i> ' + message;
    
    // Add to page
    document.body.appendChild(notification);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        notification.remove();
    }, 5000);
}
</script>

</body>
</html>