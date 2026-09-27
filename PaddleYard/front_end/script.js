document.addEventListener('DOMContentLoaded', () => {
    const currentPage = window.location.pathname.split('/').pop() || '';

    document.querySelectorAll('.nav-item').forEach((item) => {
        const href = item.getAttribute('href');

        if (href && currentPage.endsWith(href)) {
            item.classList.add('active');
        } else {
            item.classList.remove('active');
        }
    });
});
