// Theme Toggle - Market Plier
console.log('Theme toggle script loaded');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM ready');
    
    const toggle = document.getElementById('themeToggle');
    const html = document.documentElement;
    
    if (!toggle) {
        console.error('Toggle not found!');
        return;
    }
    
    console.log('Toggle found:', toggle);
    
    // Get stored theme or use system preference
    let theme = localStorage.getItem('theme');
    console.log('Stored theme:', theme);
    
    if (!theme) {
        theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        console.log('System preference:', theme);
    }
    
    // Apply theme on load
    html.setAttribute('data-bs-theme', theme);
    toggle.checked = (theme === 'dark');
    console.log('Applied theme:', theme);
    
    // Toggle event
    toggle.addEventListener('change', function() {
        const newTheme = this.checked ? 'dark' : 'light';
        html.setAttribute('data-bs-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        console.log('Theme changed to:', newTheme);
    });
});
