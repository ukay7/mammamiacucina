document.addEventListener('submit', function (event) {
    if (!event.target.matches('[data-static-preview]')) return;
    event.preventDefault();
    window.alert('This is a static theme preview. Form submissions will be connected in the next development phase.');
});
