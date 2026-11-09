(function () {
    const storageKey = 'jengo-theme';
    const savedTheme = window.localStorage.getItem(storageKey);
    const prefersNight = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    let theme = savedTheme === 'day' || savedTheme === 'night'
        ? savedTheme
        : (prefersNight ? 'night' : 'day');

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'theme-mode-toggle';
    toggle.addEventListener('click', function () {
        theme = theme === 'night' ? 'day' : 'night';
        window.localStorage.setItem(storageKey, theme);
        applyTheme();
    });

    function applyTheme() {
        document.body.dataset.theme = theme;
        const nextTheme = theme === 'night' ? 'day' : 'night';
        toggle.textContent = theme === 'night' ? '☀ Day' : '☾ Night';
        toggle.setAttribute('aria-label', 'Switch to ' + nextTheme + ' mode');
        toggle.setAttribute('title', 'Switch to ' + nextTheme + ' mode');
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.body.appendChild(toggle);
        applyTheme();
    });
})();
