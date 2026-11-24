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

const prototypeInput = document.querySelector('#prototype');
const prototypePreview = document.querySelector('[data-prototype-preview]');
const prototypePreviewImage = document.querySelector('[data-prototype-preview-image]');
const prototypeFileName = document.querySelector('[data-prototype-file-name]');
const prototypeFileSize = document.querySelector('[data-prototype-file-size]');
const prototypeStatus = document.querySelector('[data-prototype-status]');

if (
    prototypeInput instanceof HTMLInputElement
    && prototypePreview instanceof HTMLElement
    && prototypePreviewImage instanceof HTMLImageElement
    && prototypeFileName instanceof HTMLElement
    && prototypeFileSize instanceof HTMLElement
    && prototypeStatus instanceof HTMLElement
) {
    let previewImageUrl = null;

    prototypeInput.addEventListener('change', () => {
        const selectedFile = prototypeInput.files?.[0];

        if (!selectedFile) {
            prototypePreview.hidden = true;
            prototypePreviewImage.hidden = true;
            prototypePreviewImage.removeAttribute('src');
            prototypeFileName.textContent = '';
            prototypeFileSize.textContent = '';
            prototypeStatus.textContent = prototypeStatus.dataset.emptyMessage ?? 'Belum ada file dipilih.';

            if (previewImageUrl) {
                URL.revokeObjectURL(previewImageUrl);
                previewImageUrl = null;
            }

            return;
        }

        prototypePreview.hidden = false;
        prototypeFileName.textContent = selectedFile.name;

        const sizeInMegabytes = selectedFile.size / (1024 * 1024);
        prototypeFileSize.textContent = sizeInMegabytes < 1
            ? `${Math.round(selectedFile.size / 1024)} KB`
            : `${sizeInMegabytes.toLocaleString('id-ID', { maximumFractionDigits: 2 })} MB`;

        if (previewImageUrl) {
            URL.revokeObjectURL(previewImageUrl);
            previewImageUrl = null;
        }

        if (selectedFile.type.startsWith('image/')) {
            previewImageUrl = URL.createObjectURL(selectedFile);
            prototypePreviewImage.src = previewImageUrl;
            prototypePreviewImage.alt = `Pratinjau ${selectedFile.name}`;
            prototypePreviewImage.hidden = false;
        } else {
            prototypePreviewImage.hidden = true;
            prototypePreviewImage.removeAttribute('src');
        }

        const maximumSizeInBytes = (selectedFile.type.startsWith('image/') ? 5 : 30) * 1024 * 1024;
        prototypeStatus.textContent = selectedFile.size > maximumSizeInBytes
            ? `Ukuran file melewati batas ${selectedFile.type.startsWith('image/') ? '5 MB' : '30 MB'}.`
            : 'File siap dikirim saat submission disimpan.';
    });

    const submissionForm = prototypeInput.form;
    const submitButton = submissionForm?.querySelector('button[type="submit"]');
    const submitLabel = submitButton?.querySelector('[data-submission-submit-label]');

    if (submissionForm && submitButton instanceof HTMLButtonElement && submitLabel instanceof HTMLElement) {
        submissionForm.addEventListener('submit', () => {
            const hasSelectedFile = Boolean(prototypeInput.files?.length);

            prototypeStatus.textContent = hasSelectedFile
                ? 'Mengunggah file dan menyimpan submission…'
                : 'Menyimpan submission…';
            submitLabel.textContent = hasSelectedFile ? 'Mengunggah…' : 'Menyimpan…';
            submitButton.disabled = true;
            submissionForm.setAttribute('aria-busy', 'true');
        });
    }
}
