(() => {
    const button = document.getElementById('print-catalogue');
    const status = document.getElementById('catalogue-print-status');
    const images = [...document.querySelectorAll('img')];
    const settled = new Set();
    const ready = images.map(image => new Promise(resolve => {
        const finish = () => {
            if (settled.has(image)) return;
            settled.add(image);
            if (!image.naturalWidth && image.classList.contains('catalogue-product-image')) {
                image.hidden = true;
                image.nextElementSibling.hidden = false;
            }
            resolve();
        };
        image.addEventListener('load', finish, {once: true});
        image.addEventListener('error', finish, {once: true});
        if (image.complete) finish();
    }));
    button.addEventListener('click', async () => {
        if (button.disabled) return;
        button.disabled = true;
        status.textContent = 'Preparing product images for printing…';
        let timer;
        const completed = await Promise.race([
            Promise.all(ready).then(() => true),
            new Promise(resolve => { timer = setTimeout(() => resolve(false), 15000); }),
        ]);
        clearTimeout(timer);
        button.disabled = false;
        if (!completed) {
            status.textContent = 'Images are still loading. Please try Print again in a moment.';
            return;
        }
        status.textContent = 'Images ready. Choose your printer or Save as PDF.';
        window.print();
    });
})();
