/**
 * RIDEZO COMBINED JAVASCRIPT
 * Handles: Scroll Reveals, Navbar Effects, and Login Dropdown Logic
 */

// 1. SCROLL REVEAL ANIMATIONS
function reveal() {
    const reveals = document.querySelectorAll(".reveal, .reveal-stagger");

    for (let i = 0; i < reveals.length; i++) {
        const windowHeight = window.innerHeight;
        const elementTop = reveals[i].getBoundingClientRect().top;
        const elementVisible = 100; // Trigger point (px)

        if (elementTop < windowHeight - elementVisible) {
            reveals[i].classList.add("active");
        }
    }
}

// 2. NAVBAR & MULTI-SCROLL HANDLER
window.addEventListener("scroll", () => {
    // Run Reveal Logic
    reveal();

    // Navbar Shadow & Padding Logic
    const nav = document.querySelector('nav');
    if (nav) {
        if (window.pageYOffset > 50) {
            nav.classList.add('shadow-xl', 'bg-white'); // Added bg-white to ensure transparency looks good
            nav.style.padding = "5px 0";
        } else {
            nav.classList.remove('shadow-xl');
            nav.style.padding = "0";
        }
    }
});

// 3. LOGIN DROPDOWN LOGIC
function toggleLoginDropdown() {
    const dropdown = document.getElementById('loginDropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

function switchForms() {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    
    if (loginForm && signupForm) {
        loginForm.classList.toggle('hidden');
        signupForm.classList.toggle('hidden');
    }
}

// 4. GLOBAL CLICK HANDLER (Close dropdown when clicking outside)
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('loginDropdown');
    if (!dropdown) return;

    // Check if the user clicked the Login button or inside the dropdown
    const isClickInside = dropdown.contains(event.target);
    const isLoginBtn = event.target.closest('button') && 
                       (event.target.closest('button').innerText.includes('Login') || 
                        event.target.closest('button').onclick?.toString().includes('toggleLoginDropdown'));

    if (!dropdown.classList.contains('hidden')) {
        if (!isClickInside && !isLoginBtn) {
            dropdown.classList.add('hidden');
        }
    }
});

// 5. INITIALIZATION ON LOAD
document.addEventListener("DOMContentLoaded", () => {
    reveal(); // Show items already in view on refresh
    
    // Auto-hide PHP Toasts after 5 seconds
    setTimeout(() => {
        const toasts = document.querySelectorAll('.bg-red-600, .bg-blue-600');
        toasts.forEach(toast => {
            toast.style.transition = 'all 0.5s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-20px)';
            setTimeout(() => toast.remove(), 500);
        });
    }, 5000);
});