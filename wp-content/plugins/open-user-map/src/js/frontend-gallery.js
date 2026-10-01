// Initialize Masonry for each gallery
const galleries = document.querySelectorAll('.open-user-map-image-gallery');
    
galleries.forEach(function(gallery) {
    // Get all items except pagination
    const container = document.createElement('div');
    container.className = 'oum-gallery-items-container';
    const items = gallery.querySelectorAll('.oum-gallery-item');
    const pagination = gallery.querySelector('.oum-gallery-pagination');

    // Move items to container
    items.forEach(item => container.appendChild(item));
    gallery.insertBefore(container, pagination);

    // Initialize Masonry with options
    const masonry = new Masonry(container, {
        itemSelector: '.oum-gallery-item',
        columnWidth: '.oum-gallery-item',
        percentPosition: true,
        gutter: 20,
        transitionDuration: 0
    });

    // Initialize imagesLoaded
    imagesLoaded(container, function() {
        masonry.layout();
        // Show gallery after layout is complete
        gallery.style.opacity = 1;
    });

    // Update layout after each image loads
    const images = container.querySelectorAll('img');
    images.forEach(function(img) {
        img.addEventListener('load', function() {
            masonry.layout();
        });
    });
});