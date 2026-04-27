// frontend/js/app.js
const API_BASE = 'http://localhost/readzone';

async function apiCall(endpoint, method = 'GET', body = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'include' // Important for PHP Sessions
    };

    if (body) {
        options.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(`${API_BASE}${endpoint}`, options);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'API Request Failed');
        }
        return data;
    } catch (error) {
        alert(error.message);
        throw error;
    }
}

function getQueryParam(param) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(param);
}

// Redirect if not logged in (for protected pages)
function checkAuth(role = 'reader') {
    // Simple client-side check, real security is on backend
    // Since we use httpOnly cookies for session ID technically we can't check variable, 
    // but we can check if we had a successful login flag in localStorage for UI convenience
    if (!localStorage.getItem('user_id')) {
        window.location.href = 'login.html';
    }
}

function logout() {
    // Ideally call backend logout endpoint if exists to destroy session
    // For now clear local state
    localStorage.clear();
    window.location.href = 'index.html';
}
