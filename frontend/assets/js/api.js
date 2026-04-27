/* frontend/assets/js/api.js */

const API_BASE = 'http://localhost/readzone';

/**
 * Universal Wrapper for Fetch API
 */
async function apiCall(endpoint, method = 'GET', body = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        credentials: 'include' // Important for PHP Sessions
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    try {
        // Show loading state if needed (can implement global loader here)
        const response = await fetch(`${API_BASE}${endpoint}`, options);
        let data;

        if (response.status === 401) {
            console.warn('Unauthorized access. Redirecting to login.');
            localStorage.clear();
            window.location.href = 'index.html';
            throw new Error('Session expired. Please login again.');
        }

        // Handle non-JSON responses gracefully (e.g. fatal PHP errors)
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.indexOf("application/json") !== -1) {
            data = await response.json();
        } else {
            // Fallback for debug
            const text = await response.text();
            throw new Error(`Server Error: ${text.substring(0, 50)}...`);
        }

        if (!response.ok) {
            throw new Error(data.error || 'Request failed');
        }

        return data;
    } catch (error) {
        if (error.message.includes('Session expired')) {
            // Already handled redirection
            throw error;
        }
        showToast(error.message, 'error');
        throw error;
    }
}

/**
 * Toast Notification System
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    // Auto remove
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

/**
 * Authentication Helper
 */
function checkAuth(role = 'reader') {
    const userId = localStorage.getItem('user_id');
    const userRole = localStorage.getItem('role');

    if (!userId) {
        window.location.href = 'index.html'; // Or login page
        return;
    }

    if (role === 'admin' && userRole !== 'admin') {
        window.location.href = 'reader_dashboard.html';
        return;
    }
}

function logout() {
    localStorage.clear();
    // Using replace to prevent back button from working easily
    window.location.replace('index.html');
}

/**
 * Navbar Initialization
 */
function initNavbar() {
    console.log("Initializing Navbar...");
    const username = localStorage.getItem('username') || 'Reader';
    const role = localStorage.getItem('role');
    const navUsername = document.getElementById('nav-username');
    const adminLink = document.getElementById('admin-link');
    const navLinks = document.querySelectorAll('.nav-links a');

    if (navUsername) {
        navUsername.textContent = username;
    }

    if (adminLink && role === 'admin') {
        adminLink.classList.remove('hidden');
    }

    // Active link highlighting
    const currentPath = window.location.pathname;
    console.log("Current Path:", currentPath);

    navLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        if (!linkPath || linkPath === '#') return;

        // Reset
        link.classList.remove('active');

        // Check if current path ends with link path or exactly matches
        // This handles cases like 'reader_dashboard.html' matching '/readzone/frontend/reader_dashboard.html'
        if (currentPath.endsWith(linkPath) || (currentPath.endsWith('/') && linkPath === 'reader_dashboard.html')) {
            console.log("Setting active link:", linkPath);
            link.classList.add('active');
        }
    });
}


/**
 * UI Helper: Show Full Screen Loading Overlay
 */
function showGlobalLoading(text = 'Processing...') {
    let overlay = document.getElementById('loading-overlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'loading-overlay';
        overlay.innerHTML = `
            <div class="spinner"></div>
            <div id="loading-text" class="font-bold text-lg" style="color:var(--primary)">${text}</div>
        `;
        document.body.appendChild(overlay);
    } else {
        document.getElementById('loading-text').textContent = text;
        overlay.style.display = 'flex';
    }
}

/**
 * UI Helper: Hide Full Screen Loading Overlay
 */
function hideGlobalLoading() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}
