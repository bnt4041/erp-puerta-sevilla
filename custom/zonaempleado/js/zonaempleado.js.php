<?php
/* Copyright (C) 2025 Zona Empleado Dev
 *
 * JavaScript for Employee Zone
 */

if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB')) define('NOREQUIREDB', '1');
if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', '1');
if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', 1);
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN')) define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');

session_cache_limiter('public');

$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
    $res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
    $res = @include "../../../main.inc.php";
}
if (!$res) {
    header('Content-type: application/javascript');
    echo "/* Error loading Dolibarr */";
    exit;
}

// Define js type
header('Content-type: application/javascript');
// Important: Following code is to avoid page request by browser and PHP CPU at each Dolibarr page access.
if (empty($dolibarr_nocache)) header('Cache-Control: max-age=10800, public, must-revalidate');

?>

/* Employee Zone JavaScript Functionality */

/**
 * Polyfill for getParameterByName (from Dolibarr lib_foot.js.php)
 * Required to avoid errors when Dolibarr scripts reference this function
 */
if (typeof getParameterByName !== 'function') {
    function getParameterByName(name, url) {
        if (!url) url = window.location.href;
        name = name.replace(/[\[\]]/g, '\\$&');
        var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
            results = regex.exec(url);
        if (!results) return null;
        if (!results[2]) return '';
        return decodeURIComponent(results[2].replace(/\+/g, ' '));
    }
}

/**
 * Employee Zone namespace
 */
var ZonaEmpleado = {
    
    /**
     * Initialize the employee zone
     */
    init: function() {
        this.initMobileMenu();
        this.initDropdowns();
        this.initAnimations();
        this.initNotifications();
        this.initQuickSearch();
        
        console.log('ZonaEmpleado: Initialized successfully');
    },
    
    /**
     * Initialize mobile menu functionality
     */
    initMobileMenu: function() {
        const mobileToggle = document.querySelector('.mobile-menu-toggle');
        const mobileNav = document.querySelector('.mobile-nav');
        
        if (mobileToggle && mobileNav) {
            mobileToggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                mobileNav.classList.toggle('show');
                
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-bars');
                    icon.classList.toggle('fa-times');
                }
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!mobileToggle.contains(e.target) && !mobileNav.contains(e.target)) {
                    if (mobileNav.classList.contains('show')) {
                        mobileNav.classList.remove('show');
                        const icon = mobileToggle.querySelector('i');
                        if (icon) {
                            icon.classList.remove('fa-times');
                            icon.classList.add('fa-bars');
                        }
                    }
                }
            });
        }
    },
    
    /**
     * Initialize dropdown menus
     */
    initDropdowns: function() {
        // Buscar todos los botones con data-toggle="dropdown" o clase dropdown-toggle
        const dropdownBtns = document.querySelectorAll('[data-toggle="dropdown"], .dropdown-toggle, .user-menu-btn');
        
        dropdownBtns.forEach(function(btn) {
            const dropdown = btn.nextElementSibling;
            
            if (dropdown && dropdown.classList.contains('dropdown-menu')) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Close other dropdowns
                    document.querySelectorAll('.dropdown-menu.show').forEach(function(other) {
                        if (other !== dropdown) {
                            other.classList.remove('show');
                        }
                    });
                    
                    // Toggle current dropdown
                    dropdown.classList.toggle('show');
                });
            }
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            // Check if click is outside any dropdown
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu.show').forEach(function(dropdown) {
                    dropdown.classList.remove('show');
                });
            }
        });
        
        // Prevent dropdown from closing when clicking inside it
        document.querySelectorAll('.dropdown-menu').forEach(function(menu) {
            menu.addEventListener('click', function(e) {
                // Si es un enlace, dejamos que cierre
                if (!e.target.closest('a')) {
                    e.stopPropagation();
                }
            });
        });
    },
    
    /**
     * Initialize animations
     */
    initAnimations: function() {
        // Animate dashboard cards on load
        const cards = document.querySelectorAll('.dashboard-card');
        cards.forEach(function(card, index) {
            card.style.animationDelay = (index * 0.1) + 's';
            card.classList.add('fade-in');
        });
        
        // Add hover effects to interactive elements
        const interactiveElements = document.querySelectorAll('.quick-link-button, .action-btn, .extension-link');
        interactiveElements.forEach(function(element) {
            element.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });
            
            element.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    },
    
    /**
     * Initialize notification system
     */
    initNotifications: function() {
        // Check for notifications on page load
        this.checkNotifications();
        
        // Set up periodic notification checks (every 5 minutes)
        setInterval(function() {
            ZonaEmpleado.checkNotifications();
        }, 300000);
    },
    
    /**
     * Check for new notifications
     */
    checkNotifications: function() {
        // This is a placeholder for notification checking functionality
        // In a real implementation, this would make an AJAX call to check for notifications
        
        // Example notification for testing
        if (Math.random() < 0.1) { // 10% chance for demo purposes
            this.showNotification('Nueva notificación de prueba', 'info');
        }
    },
    
    /**
     * Show notification to user
     */
    showNotification: function(message, type, duration) {
        type = type || 'info';
        duration = duration || 5000;
        
        const notification = document.createElement('div');
        notification.className = 'employee-notification employee-notification-' + type;
        
        let icon = 'fa-info-circle';
        switch(type) {
            case 'success':
                icon = 'fa-check-circle';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                break;
            case 'error':
                icon = 'fa-times-circle';
                break;
        }
        
        notification.innerHTML = '<i class="fas ' + icon + '"></i> ' + message;
        
        // Add close button
        const closeBtn = document.createElement('button');
        closeBtn.className = 'notification-close';
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.style.cssText = 'background:none;border:none;color:inherit;float:right;margin-left:10px;cursor:pointer;';
        closeBtn.addEventListener('click', function() {
            notification.remove();
        });
        notification.appendChild(closeBtn);
        
        // Add to page
        document.body.appendChild(notification);
        
        // Auto-remove after duration
        setTimeout(function() {
            if (notification.parentNode) {
                notification.remove();
            }
        }, duration);
    },
    
    /**
     * Initialize quick search functionality
     */
    initQuickSearch: function() {
        const searchInput = document.querySelector('#quick-search');
        
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length > 2) {
                    searchTimeout = setTimeout(function() {
                        ZonaEmpleado.performQuickSearch(query);
                    }, 300);
                }
            });
        }
    },
    
    /**
     * Perform quick search
     */
    performQuickSearch: function(query) {
        // This is a placeholder for quick search functionality
        // In a real implementation, this would make an AJAX call to search
        
        console.log('Performing quick search for:', query);
        
        // Example: Show search suggestions
        this.showSearchSuggestions([
            {title: 'Mi Perfil', url: '/custom/zonaempleado/profile.php'},
            {title: 'Dashboard', url: '/custom/zonaempleado/index.php'}
        ]);
    },
    
    /**
     * Show search suggestions
     */
    showSearchSuggestions: function(suggestions) {
        let suggestionsContainer = document.querySelector('.search-suggestions');
        
        if (!suggestionsContainer) {
            suggestionsContainer = document.createElement('div');
            suggestionsContainer.className = 'search-suggestions';
            suggestionsContainer.style.cssText = `
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 1px solid #ddd;
                border-top: none;
                border-radius: 0 0 4px 4px;
                max-height: 300px;
                overflow-y: auto;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            `;
            
            const searchContainer = document.querySelector('#quick-search').parentNode;
            searchContainer.style.position = 'relative';
            searchContainer.appendChild(suggestionsContainer);
        }
        
        suggestionsContainer.innerHTML = '';
        
        suggestions.forEach(function(suggestion) {
            const item = document.createElement('a');
            item.href = suggestion.url;
            item.className = 'search-suggestion-item';
            item.style.cssText = `
                display: block;
                padding: 10px;
                text-decoration: none;
                color: #333;
                border-bottom: 1px solid #f0f0f0;
            `;
            item.textContent = suggestion.title;
            
            item.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'white';
            });
            
            suggestionsContainer.appendChild(item);
        });
    },
    
    /**
     * Load content dynamically via AJAX
     */
    loadContent: function(url, container, callback) {
        const xhr = new XMLHttpRequest();
        
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    if (container) {
                        container.innerHTML = xhr.responseText;
                    }
                    
                    if (callback && typeof callback === 'function') {
                        callback(xhr.responseText);
                    }
                } else {
                    ZonaEmpleado.showNotification('Error al cargar contenido', 'error');
                }
            }
        };
        
        xhr.send();
    },
    
    /**
     * Handle form submissions via AJAX
     */
    submitForm: function(form, callback) {
        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();
        
        xhr.open(form.method || 'POST', form.action || window.location.href, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            ZonaEmpleado.showNotification(response.message || 'Operación completada', 'success');
                        } else {
                            ZonaEmpleado.showNotification(response.error || 'Error en la operación', 'error');
                        }
                        
                        if (callback && typeof callback === 'function') {
                            callback(response);
                        }
                    } catch (e) {
                        // If response is not JSON, treat as success
                        ZonaEmpleado.showNotification('Operación completada', 'success');
                        
                        if (callback && typeof callback === 'function') {
                            callback({success: true, html: xhr.responseText});
                        }
                    }
                } else {
                    ZonaEmpleado.showNotification('Error en el servidor', 'error');
                }
            }
        };
        
        xhr.send(formData);
    },
    
    /**
     * Utility function to format dates
     */
    formatDate: function(date, format) {
        format = format || 'DD/MM/YYYY';
        
        const d = new Date(date);
        const day = ('0' + d.getDate()).slice(-2);
        const month = ('0' + (d.getMonth() + 1)).slice(-2);
        const year = d.getFullYear();
        
        return format.replace('DD', day).replace('MM', month).replace('YYYY', year);
    },
    
    /**
     * Utility function to debounce function calls
     */
    debounce: function(func, wait, immediate) {
        let timeout;
        
        return function executedFunction() {
            const context = this;
            const args = arguments;
            
            const later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            
            const callNow = immediate && !timeout;
            
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            
            if (callNow) func.apply(context, args);
        };
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    ZonaEmpleado.init();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ZonaEmpleado;
}