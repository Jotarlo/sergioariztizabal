/**
 * Image Position Editor
 * Visual editor for positioning custom image overlays on the map
 */
(function() {
    'use strict';

    /**
     * Image Position Editor Class
     */
    class ImagePositionEditor {
        constructor(containerId, imageUrl, initialBounds) {
            this.containerId = containerId;
            this.imageUrl = imageUrl;
            this.initialBounds = initialBounds || null;
            this.map = null;
            this.imageOverlay = null;
            this.isDragging = false;
            this.isScaling = false;
            this.dragStart = null;
            this.scaleStart = null;
            this.currentBounds = null;
            this.resizeHandleMarkers = {};
            this.isDestroyed = false;
            
            this.init();
        }

        /**
         * Initialize the editor
         */
        init() {
            const container = document.getElementById(this.containerId);
            if (!container) {
                console.error('Image Position Editor: Container not found');
                return;
            }

            // Create map container
            const mapContainer = document.createElement('div');
            mapContainer.id = this.containerId + '_map';
            mapContainer.style.width = '100%';
            mapContainer.style.height = '500px';
            mapContainer.style.border = '1px solid #ddd';
            mapContainer.style.borderRadius = '4px';
            container.appendChild(mapContainer);

            // Initialize map
            this.initMap(mapContainer);

            // Wait for map to be ready before loading image
            this.map.whenReady(() => {
                if (this.imageUrl) {
                    this.loadImage();
                }
            });
        }

        /**
         * Initialize Leaflet map
         */
        initMap(container) {
            // Initialize map centered at (0, 0) with zoom level 1
            this.map = L.map(container, {
                center: [0, 0],
                zoom: 1,
                zoomControl: true,
                attributionControl: false,
                scrollWheelZoom: false
            });

            // Add tile layer (use OpenStreetMap as default)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(this.map);
        }

        /**
         * Load image and create overlay
         */
        loadImage() {
            if (!this.map) {
                console.error('Image Position Editor: Map not initialized');
                return;
            }
            
            // Check if the image is an SVG
            const isSVG = this.imageUrl.toLowerCase().includes('.svg');
            
            if (isSVG) {
                // Handle SVG file - fetch and render as DOM elements
                this.loadSVGOverlay();
            } else {
                // Handle regular image file
                const img = new Image();
                img.onload = () => {
                    if (this.isDestroyed) return;
                    this.createImageOverlay(img);
                };
                img.onerror = () => {
                    console.error('Image Position Editor: Failed to load image');
                };
                img.src = this.imageUrl;
            }
        }
        
        /**
         * Load SVG file and create overlay
         */
        loadSVGOverlay() {
            fetch(this.imageUrl)
                .then(response => response.text())
                .then(svgText => {
                    if (this.isDestroyed || !this.map) return;

                    // Create SVG element from the fetched content
                    const svgElement = this.createSVGElement(svgText);
                    if (!svgElement) {
                        console.error('Image Position Editor: Cannot create SVG layer - invalid SVG element');
                        return;
                    }
                    
                    // Calculate initial bounds
                    let bounds;
                    if (this.initialBounds && 
                        this.initialBounds.north && 
                        this.initialBounds.south && 
                        this.initialBounds.east && 
                        this.initialBounds.west) {
                        bounds = [
                            [this.initialBounds.north, this.initialBounds.west],
                            [this.initialBounds.south, this.initialBounds.east]
                        ];
                    } else {
                        // Default initial bounds centered at (0, 0)
                        const initialLatRange = 60;
                        const initialLngRange = 60;
                        bounds = [
                            [initialLatRange / 2, -initialLngRange / 2],
                            [-initialLatRange / 2, initialLngRange / 2]
                        ];
                    }
                    
                    // Create SVG overlay
                    this.imageOverlay = L.svgOverlay(svgElement, bounds, {
                        opacity: 1.0,
                        interactive: true
                    }).addTo(this.map);
                    
                    // Store original opacity
                    this.originalOpacity = 1.0;
                    
                    // Store current bounds
                    this.currentBounds = this.imageOverlay.getBounds();
                    
                    // Fit map to show the image with some padding
                    this.map.fitBounds(this.imageOverlay.getBounds(), {
                        padding: [50, 50]
                    });
                    
                    // Make overlay draggable and scalable
                    setTimeout(() => {
                        this.makeOverlayInteractive();
                    }, 300);
                })
                .catch(error => {
                    console.error('Image Position Editor: Error fetching SVG file:', error);
                });
        }
        
        /**
         * Create SVG element from text
         */
        createSVGElement(svgText) {
            // Create a temporary div to parse the SVG
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = svgText;
            const svgElement = tempDiv.querySelector('svg');
            
            if (!svgElement) {
                console.warn('Image Position Editor: No valid SVG element found in SVG text');
                return null;
            }
            
            // Preserve the original viewBox if it exists
            // If missing, try to create it from width/height attributes
            if (!svgElement.getAttribute('viewBox')) {
                const width = svgElement.getAttribute('width');
                const height = svgElement.getAttribute('height');
                if (width && height) {
                    // Remove units if present (e.g., "1580px" -> "1580")
                    const widthNum = parseFloat(width);
                    const heightNum = parseFloat(height);
                    if (!isNaN(widthNum) && !isNaN(heightNum)) {
                        svgElement.setAttribute('viewBox', `0 0 ${widthNum} ${heightNum}`);
                    } else {
                        svgElement.setAttribute('viewBox', '0 0 1000 1200');
                    }
                } else {
                    svgElement.setAttribute('viewBox', '0 0 1000 1200');
                }
            }
            
            // Ensure the SVG has proper styling for overlay
            svgElement.style.width = '100%';
            svgElement.style.height = '100%';
            svgElement.style.display = 'block';
            
            // Ensure the SVG fills the entire bounds area to prevent cropping
            svgElement.setAttribute('preserveAspectRatio', 'none');
            
            return svgElement;
        }

        /**
         * Create image overlay with initial bounds
         */
        createImageOverlay(img) {
            if (this.isDestroyed || !this.map) return;

            const imageWidth = img.naturalWidth;
            const imageHeight = img.naturalHeight;
            const imageAspectRatio = imageHeight / imageWidth;

            let bounds;
            
            // If initial bounds exist, use them
            if (this.initialBounds && 
                this.initialBounds.north && 
                this.initialBounds.south && 
                this.initialBounds.east && 
                this.initialBounds.west) {
                bounds = [
                    [this.initialBounds.north, this.initialBounds.west],
                    [this.initialBounds.south, this.initialBounds.east]
                ];
            } else {
                // Calculate initial bounds centered at (0, 0) maintaining aspect ratio
                // Use a reasonable initial size (e.g., 60° latitude range)
                const initialLatRange = 60;
                const initialLngRange = initialLatRange / imageAspectRatio;
                
                bounds = [
                    [initialLatRange / 2, -initialLngRange / 2],
                    [-initialLatRange / 2, initialLngRange / 2]
                ];
            }

            // Create image overlay
            this.imageOverlay = L.imageOverlay(this.imageUrl, bounds, {
                opacity: 1.0,
                interactive: true
            }).addTo(this.map);
            
            // Store original opacity for restoring after drag/scale
            this.originalOpacity = 1.0;

            // Store current bounds
            this.currentBounds = this.imageOverlay.getBounds();

            // Fit map to show the image with some padding
            this.map.fitBounds(this.imageOverlay.getBounds(), {
                padding: [50, 50]
            });

            // Make overlay draggable and scalable
            setTimeout(() => {
                this.makeOverlayInteractive();
            }, 300);
        }

        /**
         * Make overlay interactive (draggable and scalable)
         */
        makeOverlayInteractive() {
            if (this.isDestroyed || !this.imageOverlay) return;

            setTimeout(() => {
                if (this.isDestroyed || !this.imageOverlay || !this.map) return;

                const overlayElement = this.imageOverlay.getElement();
                if (!overlayElement) {
                    setTimeout(() => this.makeOverlayInteractive(), 100);
                    return;
                }

                // Add cursor styles
                overlayElement.style.cursor = 'move';
                overlayElement.style.position = 'relative';
                overlayElement.style.zIndex = '1000';
                
                // Handle both image overlays (img tag) and SVG overlays (svg element)
                const img = overlayElement.querySelector('img');
                const svg = overlayElement.querySelector('svg');
                
                if (img) {
                    img.style.pointerEvents = 'auto';
                    img.style.cursor = 'move';
                } else if (svg) {
                    svg.style.pointerEvents = 'auto';
                    svg.style.cursor = 'move';
                }

                // Add resize handles and make draggable
                this.addResizeHandlesToMap();
                this.makeDraggable(overlayElement);
                
                // Setup listeners for manual input synchronization
                this.setupInputListeners();
            }, 300);
        }

        /**
         * Add resize handles to map (as markers at corners)
         */
        addResizeHandlesToMap() {
            if (this.isDestroyed || !this.imageOverlay || !this.map) return;
            
            setTimeout(() => {
                if (this.isDestroyed || !this.imageOverlay || !this.map || !this.map._panes || !this.map._panes.markerPane) {
                    return;
                }

                const bounds = this.imageOverlay.getBounds();
                if (!bounds || !bounds.isValid()) {
                    setTimeout(() => this.addResizeHandlesToMap(), 100);
                    return;
                }
                
                const handles = {
                    'nw': bounds.getNorthWest(),
                    'ne': bounds.getNorthEast(),
                    'sw': bounds.getSouthWest(),
                    'se': bounds.getSouthEast()
                };
                
                this.resizeHandleMarkers = {};
                
                Object.keys(handles).forEach(handle => {
                    const handleIcon = L.divIcon({
                        className: 'oum-resize-handle-marker',
                        html: `<div class="oum-resize-handle oum-resize-handle-${handle}" style="
                            width: 20px;
                            height: 20px;
                            background: #fff;
                            border: 3px solid #0078d4;
                            border-radius: 50%;
                            box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                            cursor: ${handle}-resize;
                            pointer-events: auto;
                        "></div>`,
                        iconSize: [20, 20],
                        iconAnchor: [10, 10]
                    });
                    
                    const marker = L.marker(handles[handle], {
                        icon: handleIcon,
                        draggable: false,
                        zIndexOffset: 10000,
                        interactive: true
                    }).addTo(this.map);
                    
                    const handleElement = marker.getElement().querySelector('.oum-resize-handle');
                    if (handleElement) {
                        handleElement.setAttribute('data-handle', handle);
                        handleElement.addEventListener('mousedown', (e) => {
                            e.stopPropagation();
                            e.preventDefault();
                            this.startScaling(e, handle);
                        });
                    }
                    
                    marker.on('mousedown', (e) => {
                        e.originalEvent.stopPropagation();
                        e.originalEvent.preventDefault();
                        this.startScaling(e.originalEvent, handle);
                    });
                    
                    this.resizeHandleMarkers[handle] = marker;
                });
            }, 200);
        }
        
        /**
         * Update resize handles position (called after bounds change)
         */
        updateResizeHandles() {
            if (!this.imageOverlay || !this.resizeHandleMarkers) return;
            
            const bounds = this.imageOverlay.getBounds();
            const positions = {
                'nw': bounds.getNorthWest(),
                'ne': bounds.getNorthEast(),
                'sw': bounds.getSouthWest(),
                'se': bounds.getSouthEast()
            };
            
            Object.keys(positions).forEach(handle => {
                if (this.resizeHandleMarkers[handle]) {
                    this.resizeHandleMarkers[handle].setLatLng(positions[handle]);
                }
            });
        }

        /**
         * Make overlay draggable
         */
        makeDraggable(element) {
            element.addEventListener('mousedown', (e) => {
                // Don't start drag if clicking on resize handle
                if (e.target.classList.contains('oum-resize-handle')) {
                    return;
                }
                this.startDragging(e);
            });
        }

        /**
         * Start dragging
         */
        startDragging(e) {
            this.isDragging = true;
            const currentBounds = this.imageOverlay.getBounds();
            
            // Calculate center point of image
            const centerLat = (currentBounds.getNorth() + currentBounds.getSouth()) / 2;
            const centerLng = (currentBounds.getEast() + currentBounds.getWest()) / 2;
            
            // Store start position in pixel coordinates to avoid Mercator distortion
            const startContainerPoint = this.map.mouseEventToContainerPoint(e);
            const centerContainerPoint = this.map.latLngToContainerPoint([centerLat, centerLng]);
            
            // Get visual pixel dimensions to maintain size during drag
            const overlayElement = this.imageOverlay.getElement();
            let visualWidth = overlayElement ? (overlayElement.offsetWidth || overlayElement.clientWidth) : 0;
            let visualHeight = overlayElement ? (overlayElement.offsetHeight || overlayElement.clientHeight) : 0;
            
            // Fallback: calculate from bounds if element size not available
            if (visualWidth === 0 || visualHeight === 0) {
                const nw = this.map.latLngToContainerPoint([currentBounds.getNorth(), currentBounds.getWest()]);
                const se = this.map.latLngToContainerPoint([currentBounds.getSouth(), currentBounds.getEast()]);
                visualWidth = Math.abs(se.x - nw.x);
                visualHeight = Math.abs(se.y - nw.y);
            }
            
            this.dragStart = {
                startContainerPoint: startContainerPoint,
                centerContainerPoint: centerContainerPoint,
                visualWidth: visualWidth,
                visualHeight: visualHeight
            };

            // Set opacity to 50% during drag for visual feedback
            if (this.imageOverlay) {
                this.imageOverlay.setOpacity(0.5);
            }

            // Disable map dragging while dragging overlay
            this.map.dragging.disable();
            
            document.addEventListener('mousemove', this.onDragMove.bind(this));
            document.addEventListener('mouseup', this.onDragEnd.bind(this));
            e.preventDefault();
            e.stopPropagation();
        }

        /**
         * Clamp bounds to valid world coordinates
         * Latitude: -90 to 90, Longitude: -180 to 180
         */
        clampBounds(bounds) {
            let north = Math.min(Math.max(bounds.getNorth(), -90), 90);
            let south = Math.min(Math.max(bounds.getSouth(), -90), 90);
            let east = Math.min(Math.max(bounds.getEast(), -180), 180);
            let west = Math.min(Math.max(bounds.getWest(), -180), 180);
            
            // Ensure valid bounds after clamping (north > south, east > west)
            if (north <= south) {
                const centerLat = (north + south) / 2;
                north = Math.min(centerLat + 0.0001, 90);
                south = Math.max(centerLat - 0.0001, -90);
            }
            
            if (east <= west) {
                const centerLng = (east + west) / 2;
                east = Math.min(centerLng + 0.0001, 180);
                west = Math.max(centerLng - 0.0001, -180);
            }
            
            return L.latLngBounds([south, west], [north, east]);
        }

        /**
         * Handle drag move
         */
        onDragMove(e) {
            if (!this.isDragging || !this.imageOverlay) return;

            const currentContainerPoint = this.map.mouseEventToContainerPoint(e);
            const pixelDeltaX = currentContainerPoint.x - this.dragStart.startContainerPoint.x;
            const pixelDeltaY = currentContainerPoint.y - this.dragStart.startContainerPoint.y;
            
            // Calculate new center position
            const newCenterContainerPoint = L.point(
                this.dragStart.centerContainerPoint.x + pixelDeltaX,
                this.dragStart.centerContainerPoint.y + pixelDeltaY
            );
            
            // Recalculate bounds from pixel dimensions to maintain visual size
            const halfWidth = this.dragStart.visualWidth / 2;
            const halfHeight = this.dragStart.visualHeight / 2;
            
            const topLeftContainer = L.point(
                newCenterContainerPoint.x - halfWidth,
                newCenterContainerPoint.y - halfHeight
            );
            const bottomRightContainer = L.point(
                newCenterContainerPoint.x + halfWidth,
                newCenterContainerPoint.y + halfHeight
            );
            
            const topLeftLatLng = this.map.containerPointToLatLng(topLeftContainer);
            const bottomRightLatLng = this.map.containerPointToLatLng(bottomRightContainer);
            
            let newBounds = L.latLngBounds(
                [bottomRightLatLng.lat, topLeftLatLng.lng],
                [topLeftLatLng.lat, bottomRightLatLng.lng]
            );

            // Clamp bounds to valid world coordinates
            newBounds = this.clampBounds(newBounds);

            this.imageOverlay.setBounds(newBounds);
            this.currentBounds = newBounds;
            this.updateResizeHandles();
            this.updateBoundsInputs();
            
            e.preventDefault();
        }

        /**
         * End dragging
         */
        onDragEnd() {
            this.isDragging = false;
            
            // Restore original opacity
            if (this.imageOverlay) {
                this.imageOverlay.setOpacity(this.originalOpacity);
            }
            
            // Re-enable map dragging
            this.map.dragging.enable();
            document.removeEventListener('mousemove', this.onDragMove.bind(this));
            document.removeEventListener('mouseup', this.onDragEnd.bind(this));
        }

        /**
         * Start scaling
         */
        startScaling(e, handle) {
            this.isScaling = true;
            const startBounds = this.imageOverlay.getBounds();
            
            // Get visual pixel dimensions to avoid Mercator distortion
            const overlayElement = this.imageOverlay.getElement();
            let visualWidth = overlayElement ? (overlayElement.offsetWidth || overlayElement.clientWidth) : 0;
            let visualHeight = overlayElement ? (overlayElement.offsetHeight || overlayElement.clientHeight) : 0;
            
            // Fallback: calculate from bounds if element size not available
            if (visualWidth === 0 || visualHeight === 0) {
                const nw = this.map.latLngToContainerPoint([startBounds.getNorth(), startBounds.getWest()]);
                const se = this.map.latLngToContainerPoint([startBounds.getSouth(), startBounds.getEast()]);
                visualWidth = Math.abs(se.x - nw.x);
                visualHeight = Math.abs(se.y - nw.y);
            }
            
            // Store corner positions in container coordinates for proportional scaling
            const nwContainer = this.map.latLngToContainerPoint([startBounds.getNorth(), startBounds.getWest()]);
            const neContainer = this.map.latLngToContainerPoint([startBounds.getNorth(), startBounds.getEast()]);
            const swContainer = this.map.latLngToContainerPoint([startBounds.getSouth(), startBounds.getWest()]);
            const seContainer = this.map.latLngToContainerPoint([startBounds.getSouth(), startBounds.getEast()]);
            
            this.scaleStart = {
                containerPoint: this.map.mouseEventToContainerPoint(e),
                handle: handle,
                bounds: startBounds,
                visualAspectRatio: visualHeight / visualWidth,
                nwContainer: nwContainer,
                neContainer: neContainer,
                swContainer: swContainer,
                seContainer: seContainer
            };

            // Set opacity to 50% during scale for visual feedback
            if (this.imageOverlay) {
                this.imageOverlay.setOpacity(0.5);
            }

            this.map.dragging.disable();
            document.addEventListener('mousemove', this.onScaleMove.bind(this));
            document.addEventListener('mouseup', this.onScaleEnd.bind(this));
            document.addEventListener('keydown', this.onScaleKeyDown.bind(this));
            document.addEventListener('keyup', this.onScaleKeyUp.bind(this));
            e.preventDefault();
            e.stopPropagation();
        }

        /**
         * Handle scale move
         */
        onScaleMove(e) {
            if (!this.isScaling || !this.imageOverlay) return;

            const handle = this.scaleStart.handle;
            let north = this.scaleStart.bounds.getNorth();
            let south = this.scaleStart.bounds.getSouth();
            let east = this.scaleStart.bounds.getEast();
            let west = this.scaleStart.bounds.getWest();

            // Check if Shift key is pressed to maintain aspect ratio
            const maintainAspectRatio = e.shiftKey || this.shiftKeyPressed;

            if (maintainAspectRatio) {
                // Use pixel coordinates to avoid Mercator distortion
                const currentContainerPoint = this.map.mouseEventToContainerPoint(e);
                const pixelDeltaX = currentContainerPoint.x - this.scaleStart.containerPoint.x;
                const pixelDeltaY = currentContainerPoint.y - this.scaleStart.containerPoint.y;
                
                // Get fixed point (opposite corner)
                const fixedContainerPoint = this.getFixedCorner(handle);
                
                // Get starting corner position
                const startCornerContainer = this.getStartCorner(handle);
                
                // Calculate new corner position
                const newCornerContainer = L.point(
                    startCornerContainer.x + pixelDeltaX,
                    startCornerContainer.y + pixelDeltaY
                );
                
                // Calculate pixel distances from fixed point
                const pixelWidth = Math.abs(newCornerContainer.x - fixedContainerPoint.x);
                const pixelHeight = Math.abs(newCornerContainer.y - fixedContainerPoint.y);
                
                // Determine primary dimension and calculate final dimensions maintaining aspect ratio
                let finalPixelWidth, finalPixelHeight;
                if (pixelHeight / this.scaleStart.visualAspectRatio > pixelWidth) {
                    finalPixelHeight = pixelHeight;
                    finalPixelWidth = pixelHeight / this.scaleStart.visualAspectRatio;
                } else {
                    finalPixelWidth = pixelWidth;
                    finalPixelHeight = pixelWidth * this.scaleStart.visualAspectRatio;
                }
                
                // Calculate new corner positions relative to fixed point
                const corners = this.calculateNewCorners(handle, fixedContainerPoint, finalPixelWidth, finalPixelHeight);
                
                // Convert back to lat/lng bounds
                const nwLatLng = this.map.containerPointToLatLng(corners.nw);
                const neLatLng = this.map.containerPointToLatLng(corners.ne);
                const swLatLng = this.map.containerPointToLatLng(corners.sw);
                const seLatLng = this.map.containerPointToLatLng(corners.se);
                
                north = Math.max(nwLatLng.lat, neLatLng.lat);
                south = Math.min(swLatLng.lat, seLatLng.lat);
                east = Math.max(neLatLng.lng, seLatLng.lng);
                west = Math.min(nwLatLng.lng, swLatLng.lng);
            } else {
                // Normal scaling without aspect ratio lock
                const currentLatLng = this.map.mouseEventToLatLng(e);
                const startLatLng = this.map.containerPointToLatLng(this.scaleStart.containerPoint);
                const latDelta = currentLatLng.lat - startLatLng.lat;
                const lngDelta = currentLatLng.lng - startLatLng.lng;
                
                if (handle.includes('n')) north += latDelta;
                if (handle.includes('s')) south += latDelta;
                if (handle.includes('w')) west += lngDelta;
                if (handle.includes('e')) east += lngDelta;
            }

            if (north > south && east > west) {
                let newBounds = L.latLngBounds([south, west], [north, east]);
                
                // Clamp bounds to valid world coordinates
                newBounds = this.clampBounds(newBounds);
                
                this.imageOverlay.setBounds(newBounds);
                this.currentBounds = newBounds;
                this.updateResizeHandles();
                this.updateBoundsInputs();
            }
            e.preventDefault();
        }

        /**
         * Get fixed corner (opposite of handle being dragged)
         */
        getFixedCorner(handle) {
            switch (handle) {
                case 'nw': return this.scaleStart.seContainer;
                case 'ne': return this.scaleStart.swContainer;
                case 'sw': return this.scaleStart.neContainer;
                case 'se': return this.scaleStart.nwContainer;
            }
        }

        /**
         * Get starting corner position for handle
         */
        getStartCorner(handle) {
            switch (handle) {
                case 'nw': return this.scaleStart.nwContainer;
                case 'ne': return this.scaleStart.neContainer;
                case 'sw': return this.scaleStart.swContainer;
                case 'se': return this.scaleStart.seContainer;
            }
        }

        /**
         * Calculate new corner positions maintaining aspect ratio
         */
        calculateNewCorners(handle, fixedPoint, width, height) {
            switch (handle) {
                case 'nw':
                    return {
                        se: fixedPoint,
                        nw: L.point(fixedPoint.x - width, fixedPoint.y - height),
                        ne: L.point(fixedPoint.x, fixedPoint.y - height),
                        sw: L.point(fixedPoint.x - width, fixedPoint.y)
                    };
                case 'ne':
                    return {
                        sw: fixedPoint,
                        ne: L.point(fixedPoint.x + width, fixedPoint.y - height),
                        nw: L.point(fixedPoint.x, fixedPoint.y - height),
                        se: L.point(fixedPoint.x + width, fixedPoint.y)
                    };
                case 'sw':
                    return {
                        ne: fixedPoint,
                        sw: L.point(fixedPoint.x - width, fixedPoint.y + height),
                        nw: L.point(fixedPoint.x - width, fixedPoint.y),
                        se: L.point(fixedPoint.x, fixedPoint.y + height)
                    };
                case 'se':
                    return {
                        nw: fixedPoint,
                        se: L.point(fixedPoint.x + width, fixedPoint.y + height),
                        ne: L.point(fixedPoint.x + width, fixedPoint.y),
                        sw: L.point(fixedPoint.x, fixedPoint.y + height)
                    };
            }
        }

        /**
         * Handle key down during scaling (for Shift key detection)
         */
        onScaleKeyDown(e) {
            if (e.key === 'Shift') {
                this.shiftKeyPressed = true;
            }
        }

        /**
         * Handle key up during scaling
         */
        onScaleKeyUp(e) {
            if (e.key === 'Shift') {
                this.shiftKeyPressed = false;
            }
        }

        /**
         * End scaling
         */
        onScaleEnd() {
            this.isScaling = false;
            this.shiftKeyPressed = false;
            
            // Restore original opacity
            if (this.imageOverlay) {
                this.imageOverlay.setOpacity(this.originalOpacity);
            }
            
            // Re-enable map dragging
            this.map.dragging.enable();
            document.removeEventListener('mousemove', this.onScaleMove.bind(this));
            document.removeEventListener('mouseup', this.onScaleEnd.bind(this));
            document.removeEventListener('keydown', this.onScaleKeyDown.bind(this));
            document.removeEventListener('keyup', this.onScaleKeyUp.bind(this));
        }

        /**
         * Validate bounds are within valid world coordinates
         * Returns array of field names with errors
         */
        validateBounds(bounds) {
            const errors = [];
            
            // Validate latitude ranges (-90 to 90)
            if (bounds.north > 90 || bounds.north < -90) errors.push('north');
            if (bounds.south > 90 || bounds.south < -90) errors.push('south');
            
            // Validate longitude ranges (-180 to 180)
            if (bounds.east > 180 || bounds.east < -180) errors.push('east');
            if (bounds.west > 180 || bounds.west < -180) errors.push('west');
            
            // Validate bounds logic
            if (bounds.north <= bounds.south) errors.push('north', 'south');
            if (bounds.east <= bounds.west) errors.push('east', 'west');
            
            return errors;
        }

        /**
         * Update bounds input fields
         */
        updateBoundsInputs() {
            if (!this.currentBounds) return;

            // Round to 6 decimal places to match step="0.000001"
            const bounds = {
                north: parseFloat(this.currentBounds.getNorth().toFixed(6)),
                south: parseFloat(this.currentBounds.getSouth().toFixed(6)),
                east: parseFloat(this.currentBounds.getEast().toFixed(6)),
                west: parseFloat(this.currentBounds.getWest().toFixed(6))
            };

            // Validate bounds
            const validationErrors = this.validateBounds(bounds);

            // Update input fields if they exist
            const inputs = {
                north: document.getElementById('image_bounds_north'),
                south: document.getElementById('image_bounds_south'),
                east: document.getElementById('image_bounds_east'),
                west: document.getElementById('image_bounds_west')
            };

            Object.keys(inputs).forEach(key => {
                if (inputs[key]) {
                    // Only update if value actually changed to avoid triggering our own listener
                    const newValue = parseFloat(bounds[key].toFixed(6)).toFixed(6);
                    if (inputs[key].value !== newValue) {
                        inputs[key].value = newValue;
                    }
                    
                    // Set validation error if this field has an error
                    if (validationErrors.includes(key)) {
                        inputs[key].setCustomValidity('Value out of valid range');
                        inputs[key].classList.add('oum-invalid-bounds');
                    } else {
                        inputs[key].setCustomValidity('');
                        inputs[key].classList.remove('oum-invalid-bounds');
                    }
                }
            });

            // Update hidden field with rounded values
            const hiddenField = document.getElementById('oum_custom_image_bounds');
            if (hiddenField) {
                hiddenField.value = JSON.stringify(bounds);
            }
        }

        /**
         * Update overlay from manual input values
         */
        updateOverlayFromInputs() {
            if (!this.imageOverlay || !this.map) return;

            const inputs = {
                north: document.getElementById('image_bounds_north'),
                south: document.getElementById('image_bounds_south'),
                east: document.getElementById('image_bounds_east'),
                west: document.getElementById('image_bounds_west')
            };

            const values = {};
            let allValid = true;
            
            Object.keys(inputs).forEach(key => {
                if (!inputs[key] || inputs[key].value === '') {
                    allValid = false;
                } else {
                    const value = parseFloat(inputs[key].value);
                    if (isNaN(value)) {
                        allValid = false;
                    } else {
                        values[key] = value;
                    }
                }
            });

            if (!allValid) return;
            
            // Validate bounds
            const validationErrors = this.validateBounds(values);
            
            // Clear all validation errors first
            Object.keys(inputs).forEach(key => {
                if (inputs[key]) {
                    inputs[key].setCustomValidity('');
                    inputs[key].classList.remove('oum-invalid-bounds');
                }
            });
            
            // If there are validation errors, show them and don't update overlay
            if (validationErrors.length > 0) {
                validationErrors.forEach(key => {
                    if (inputs[key]) {
                        inputs[key].setCustomValidity('Value out of valid range');
                        inputs[key].classList.add('oum-invalid-bounds');
                    }
                });
                return;
            }

            // Clamp bounds to valid ranges before applying
            let newBounds = L.latLngBounds(
                [values.south, values.west],
                [values.north, values.east]
            );
            newBounds = this.clampBounds(newBounds);

            this.imageOverlay.setBounds(newBounds);
            this.currentBounds = newBounds;
            this.updateResizeHandles();
        }

        /**
         * Setup listeners for manual input synchronization
         */
        setupInputListeners() {
            const inputs = {
                north: document.getElementById('image_bounds_north'),
                south: document.getElementById('image_bounds_south'),
                east: document.getElementById('image_bounds_east'),
                west: document.getElementById('image_bounds_west')
            };

            let updateTimeout = null;
            const scheduleUpdate = () => {
                clearTimeout(updateTimeout);
                updateTimeout = setTimeout(() => {
                    this.updateOverlayFromInputs();
                }, 500);
            };

            Object.keys(inputs).forEach(key => {
                if (inputs[key]) {
                    inputs[key].addEventListener('input', scheduleUpdate);
                    inputs[key].addEventListener('blur', () => {
                        clearTimeout(updateTimeout);
                        this.updateOverlayFromInputs();
                    });
                }
            });
        }

        /**
         * Get current bounds
         */
        getBounds() {
            if (!this.currentBounds) return null;

            return {
                north: this.currentBounds.getNorth(),
                south: this.currentBounds.getSouth(),
                east: this.currentBounds.getEast(),
                west: this.currentBounds.getWest()
            };
        }

        /**
         * Destroy editor
         */
        destroy() {
            this.isDestroyed = true;

            // Remove resize handle markers
            if (this.map && this.resizeHandleMarkers) {
                Object.keys(this.resizeHandleMarkers).forEach(handle => {
                    if (this.resizeHandleMarkers[handle]) {
                        this.map.removeLayer(this.resizeHandleMarkers[handle]);
                    }
                });
            }
            
            // Remove image overlay
            if (this.map && this.imageOverlay) {
                this.map.removeLayer(this.imageOverlay);
            }
            
            if (this.map) {
                this.map.remove();
            }
            const container = document.getElementById(this.containerId);
            if (container) {
                container.innerHTML = '';
            }
        }
    }

    // Export for use in other scripts
    window.ImagePositionEditor = ImagePositionEditor;

    /**
     * Image Position Editor Integration
     * Handles image upload, editor initialization, and form interactions
     */
    const ImagePositionEditorIntegration = {
        /**
         * Initialize the integration
         */
        init() {
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.setup());
            } else {
                this.setup();
            }
        },

        /**
         * Setup all event listeners and initialize editor if needed
         */
        setup() {
            // Normalize all input values immediately on setup
            this.normalizeAllInputValues();
            this.setupBoundsSync();
            this.setupImageUpload();
            this.setupImageRemove();
            this.setupFormValidation();
            this.setupTabVisibilityHandler();
            this.initEditorIfImageExists();
        },

        /**
         * Normalize all input values on page load (convert commas to periods)
         */
        normalizeAllInputValues() {
            const boundsInputs = ['image_bounds_north', 'image_bounds_south', 'image_bounds_east', 'image_bounds_west'];
            boundsInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input && input.value) {
                    const normalized = this.normalizeDecimalSeparator(input.value);
                    if (normalized !== input.value) {
                        input.value = normalized;
                    }
                }
            });
        },

        /**
         * Normalize decimal separator (convert comma to period)
         */
        normalizeDecimalSeparator(value) {
            if (typeof value === 'string') {
                return value.replace(',', '.');
            }
            return value;
        },

        /**
         * Setup bounds synchronization between inputs and hidden field
         */
        setupBoundsSync() {
            const boundsInputs = ['image_bounds_north', 'image_bounds_south', 'image_bounds_east', 'image_bounds_west'];
            const hiddenBoundsField = document.getElementById('oum_custom_image_bounds');
            
            const updateBoundsArray = () => {
                const bounds = {};
                boundsInputs.forEach(inputId => {
                    const input = document.getElementById(inputId);
                    if (input) {
                        const fieldName = inputId.replace('image_bounds_', '');
                        // Normalize decimal separator before storing
                        bounds[fieldName] = this.normalizeDecimalSeparator(input.value);
                    }
                });
                
                if (hiddenBoundsField) {
                    hiddenBoundsField.value = JSON.stringify(bounds);
                }
            };
            
            // Normalize existing values on page load and setup event listeners
            boundsInputs.forEach(inputId => {
                const input = document.getElementById(inputId);
                if (input) {
                    // Normalize existing value on page load
                    const normalized = this.normalizeDecimalSeparator(input.value);
                    if (normalized !== input.value) {
                        input.value = normalized;
                    }
                    
                    // Normalize on input (convert comma to period)
                    input.addEventListener('input', (e) => {
                        const normalized = this.normalizeDecimalSeparator(e.target.value);
                        if (normalized !== e.target.value) {
                            e.target.value = normalized;
                        }
                        updateBoundsArray();
                    });
                    input.addEventListener('change', (e) => {
                        const normalized = this.normalizeDecimalSeparator(e.target.value);
                        if (normalized !== e.target.value) {
                            e.target.value = normalized;
                        }
                        updateBoundsArray();
                    });
                }
            });
            
            // Initial update
            updateBoundsArray();
        },

        /**
         * Setup image upload button handler
         */
        setupImageUpload() {
            const uploadButton = document.getElementById('upload_image_button');
            if (!uploadButton) return;

            uploadButton.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Get localized strings
                const strings = window.oumImagePositionEditorStrings || {};
                const chooseImageTitle = strings.chooseImageTitle || 'Choose Custom Map Image';
                const useImageText = strings.useImageText || 'Use this image';
                
                // Create media uploader
                const mediaUploader = wp.media({
                    title: chooseImageTitle,
                    button: {
                        text: useImageText
                    },
                    multiple: false,
                    library: {
                        type: ['image', 'application/svg+xml']
                    }
                });
                
                mediaUploader.on('select', () => {
                    const attachment = mediaUploader.state().get('selection').first().toJSON();
                    const imageUrlInput = document.getElementById('oum_custom_image_url');
                    const imagePreview = document.getElementById('image_preview');
                    const removeButton = document.getElementById('remove_image_button');
                    
                    if (imageUrlInput) {
                        imageUrlInput.value = attachment.url;
                    }
                    
                    // Show preview
                    if (imagePreview) {
                        imagePreview.innerHTML = '<img src="' + attachment.url + '" alt="Custom Map Image" style="max-width: 300px; max-height: 200px; border: 1px solid #ddd; border-radius: 4px;">';
                        imagePreview.style.display = 'block';
                    }
                    
                    // Show remove button
                    if (removeButton) {
                        removeButton.style.display = 'inline-block';
                    }
                    
                    // Initialize or update position editor
                    this.initImagePositionEditor(attachment.url);
                });
                
                mediaUploader.open();
            });
        },

        /**
         * Setup image remove button handler
         */
        setupImageRemove() {
            const removeButton = document.getElementById('remove_image_button');
            if (!removeButton) return;

            removeButton.addEventListener('click', (e) => {
                e.preventDefault();
                const imageUrlInput = document.getElementById('oum_custom_image_url');
                const imagePreview = document.getElementById('image_preview');
                const editorContainer = document.getElementById('oum-image-position-editor');
                
                if (imageUrlInput) {
                    imageUrlInput.value = '';
                }
                
                // Hide preview
                if (imagePreview) {
                    imagePreview.innerHTML = '';
                    imagePreview.style.display = 'none';
                }
                
                // Hide remove button
                removeButton.style.display = 'none';
                
                // Destroy position editor
                if (window.oumImagePositionEditor) {
                    window.oumImagePositionEditor.destroy();
                    window.oumImagePositionEditor = null;
                }
                
                if (editorContainer) {
                    editorContainer.style.display = 'none';
                }
            });
        },

        /**
         * Initialize image position editor
         */
        initImagePositionEditor(imageUrl) {
            const editorContainer = document.getElementById('oum-image-position-editor');
            if (!editorContainer) return;
            
            // Show editor container
            editorContainer.style.display = 'block';

            if (!this.isElementVisible(editorContainer)) {
                return;
            }
            
            // Get existing bounds if available
            let initialBounds = null;
            const hiddenBoundsField = document.getElementById('oum_custom_image_bounds');
            if (hiddenBoundsField && hiddenBoundsField.value) {
                try {
                    initialBounds = JSON.parse(hiddenBoundsField.value);
                    // Normalize decimal separators in initial bounds
                    if (initialBounds) {
                        Object.keys(initialBounds).forEach(key => {
                            if (typeof initialBounds[key] === 'string') {
                                initialBounds[key] = this.normalizeDecimalSeparator(initialBounds[key]);
                            }
                        });
                    }
                } catch (e) {
                    console.warn('Open User Map: Could not parse existing bounds');
                }
            }
            
            // Destroy existing editor if any
            if (window.oumImagePositionEditor) {
                if (window.oumImagePositionEditor.imageUrl === imageUrl) {
                    this.refreshVisibleEditor();
                    return;
                }
                window.oumImagePositionEditor.destroy();
            }
            
            // Wait for Leaflet to be fully loaded
            if (typeof L === 'undefined' || typeof ImagePositionEditor === 'undefined') {
                console.warn('Open User Map: Leaflet or ImagePositionEditor not loaded yet');
                setTimeout(() => {
                    this.initImagePositionEditor(imageUrl);
                }, 100);
                return;
            }
            
            window.oumImagePositionEditor = new ImagePositionEditor('oum-image-position-editor', imageUrl, initialBounds);
        },

        /**
         * Check if the editor can be measured by Leaflet.
         */
        isElementVisible(element) {
            return !!(element.offsetWidth || element.offsetHeight || element.getClientRects().length);
        },

        /**
         * Initialize or refresh the editor after hidden settings become visible.
         */
        refreshVisibleEditor() {
            const imageUrlInput = document.getElementById('oum_custom_image_url');
            const editorContainer = document.getElementById('oum-image-position-editor');

            if (!imageUrlInput || !imageUrlInput.value || !editorContainer) {
                return;
            }

            editorContainer.style.display = 'block';

            if (!this.isElementVisible(editorContainer)) {
                return;
            }

            if (!window.oumImagePositionEditor) {
                this.initImagePositionEditor(imageUrlInput.value);
                return;
            }

            const editor = window.oumImagePositionEditor;
            if (editor.map) {
                editor.map.invalidateSize();
                if (editor.currentBounds) {
                    editor.map.fitBounds(editor.currentBounds);
                }
            }
        },

        /**
         * Setup tab visibility handler to fix map sizing when switching tabs
         */
        setupTabVisibilityHandler() {
            // Listen for when tab-1 becomes visible
            const tab1 = document.getElementById('tab-1');
            if (tab1) {
                const observer = new MutationObserver(() => {
                    if (tab1.classList.contains('active')) {
                        setTimeout(() => {
                            this.refreshVisibleEditor();
                        }, 100);
                    }
                });
                
                observer.observe(tab1, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
        },

        /**
         * Initialize editor on page load if image already exists
         */
        initEditorIfImageExists() {
            const imageUrlInput = document.getElementById('oum_custom_image_url');
            if (imageUrlInput && imageUrlInput.value) {
                // Wait for scripts to load
                setTimeout(() => {
                    this.initImagePositionEditor(imageUrlInput.value);
                }, 500);
            }
        },

        /**
         * Validate bounds are within valid world coordinates
         * Helper function for form validation (outside of ImagePositionEditor class)
         */
        validateBoundsStatic(bounds) {
            const errors = [];
            
            // Validate latitude ranges (-90 to 90)
            if (bounds.north > 90 || bounds.north < -90) errors.push('north');
            if (bounds.south > 90 || bounds.south < -90) errors.push('south');
            
            // Validate longitude ranges (-180 to 180)
            if (bounds.east > 180 || bounds.east < -180) errors.push('east');
            if (bounds.west > 180 || bounds.west < -180) errors.push('west');
            
            // Validate bounds logic
            if (bounds.north <= bounds.south) errors.push('north', 'south');
            if (bounds.east <= bounds.west) errors.push('east', 'west');
            
            return errors;
        },

        /**
         * Setup form validation for bounds inputs
         */
        setupFormValidation() {
            const form = document.querySelector('form[method="post"]');
            if (!form) return;

            form.addEventListener('submit', (e) => {
                const boundsInputs = ['image_bounds_north', 'image_bounds_south', 'image_bounds_east', 'image_bounds_west'];
                const bounds = {};
                let hasErrors = false;
                
                // First pass: normalize and validate individual values
                boundsInputs.forEach(inputId => {
                    const input = document.getElementById(inputId);
                    if (input && input.value !== '') {
                        // Normalize decimal separator first (convert comma to period)
                        const normalizedValue = this.normalizeDecimalSeparator(input.value);
                        if (normalizedValue !== input.value) {
                            input.value = normalizedValue;
                        }
                        
                        const value = parseFloat(normalizedValue);
                        if (!isNaN(value)) {
                            // Round to exactly 6 decimal places
                            // Always use period as decimal separator
                            const rounded = Math.round(value * 1000000) / 1000000;
                            input.value = rounded.toFixed(6);
                            bounds[inputId.replace('image_bounds_', '')] = rounded;
                            input.setCustomValidity('');
                            input.classList.remove('oum-invalid-bounds');
                        } else {
                            // Invalid value - set validation error
                            input.setCustomValidity('Please enter a valid number');
                            input.classList.add('oum-invalid-bounds');
                            hasErrors = true;
                        }
                    } else if (input) {
                        // Empty value - clear validation
                        input.setCustomValidity('');
                        input.classList.remove('oum-invalid-bounds');
                    }
                });
                
                // Second pass: validate bounds ranges and logic if all values are present
                if (!hasErrors && Object.keys(bounds).length === 4) {
                    const validationErrors = this.validateBoundsStatic(bounds);
                    
                    if (validationErrors.length > 0) {
                        hasErrors = true;
                        validationErrors.forEach(key => {
                            const input = document.getElementById('image_bounds_' + key);
                            if (input) {
                                // Set appropriate error message
                                let errorMessage = 'Value out of valid range';
                                if ((key === 'north' || key === 'south') && (bounds[key] > 90 || bounds[key] < -90)) {
                                    errorMessage = 'Latitude must be between -90 and 90';
                                } else if ((key === 'east' || key === 'west') && (bounds[key] > 180 || bounds[key] < -180)) {
                                    errorMessage = 'Longitude must be between -180 and 180';
                                } else if (key === 'north' && bounds.north <= bounds.south) {
                                    errorMessage = 'North must be greater than South';
                                } else if (key === 'east' && bounds.east <= bounds.west) {
                                    errorMessage = 'East must be greater than West';
                                }
                                
                                input.setCustomValidity(errorMessage);
                                input.classList.add('oum-invalid-bounds');
                            }
                        });
                    }
                }
                
                // Prevent form submission if there are validation errors
                if (hasErrors) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Show error message to user
                    const firstErrorInput = document.querySelector('.oum-invalid-bounds');
                    if (firstErrorInput) {
                        firstErrorInput.focus();
                        firstErrorInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    
                    return false;
                }
            }, true); // Use capture phase to ensure it runs before browser validation
        }
    };

    /**
     * Toggle manual bounds inputs visibility
     * Exposed globally for onclick handler
     */
    window.toggleManualBoundsInputs = function() {
        const content = document.getElementById('manual-bounds-inputs-content');
        const icon = document.getElementById('manual-bounds-toggle-icon');
        if (content && icon) {
            if (content.style.display === 'none') {
                content.style.display = 'block';
                icon.textContent = '▼';
            } else {
                content.style.display = 'none';
                icon.textContent = '▶';
            }
        }
    };

    // Initialize integration when script loads
    ImagePositionEditorIntegration.init();
    window.oumRefreshImagePositionEditor = () => ImagePositionEditorIntegration.refreshVisibleEditor();

})();

