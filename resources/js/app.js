const themeStorageKey = 'csfest-theme';
const themeToggleButtons = document.querySelectorAll('[data-theme-toggle]');

function setTheme(theme) {
    const isNight = theme === 'night';

    document.documentElement.dataset.theme = isNight ? 'night' : 'light';

    for (const button of themeToggleButtons) {
        button.setAttribute('aria-pressed', String(isNight));
        button.setAttribute('aria-label', isNight ? 'Aktifkan mode terang' : 'Aktifkan mode malam');
        button.title = isNight ? 'Aktifkan mode terang' : 'Aktifkan mode malam';
        button.querySelector('[data-theme-icon]').textContent = isNight ? '☀' : '☾';
        button.querySelector('[data-theme-label]').textContent = isNight ? 'Mode terang' : 'Mode malam';
    }
}

setTheme(document.documentElement.dataset.theme === 'night' ? 'night' : 'light');

for (const button of themeToggleButtons) {
    button.addEventListener('click', () => {
        const nextTheme = document.documentElement.dataset.theme === 'night' ? 'light' : 'night';

        setTheme(nextTheme);

        try {
            localStorage.setItem(themeStorageKey, nextTheme);
        } catch {
            return;
        }
    });
}
