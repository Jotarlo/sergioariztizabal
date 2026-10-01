(function(){

  // Restore the extended L object (OUMLeaflet.L) to the global scope (prevents conflicts with other Leaflet instances)
  window.L = window.OUMLeaflet.L;

  const $el = jQuery('#mapRenderLocation');
  const lat = $el.data('lat');
  const lng = $el.data('lng');
  const zoom = $el.data('zoom');
  const mapStyle = $el.data('mapstyle');
  const oum_tile_provider_mapbox_key = $el.data('tile_provider_mapbox_key');
  const oum_tile_provider_carto_key = $el.data('tile_provider_carto_key');
  const marker_icon_url = $el.data('marker_icon_url');
  const marker_shadow_url = $el.data('marker_shadow_url');
  const geometryType = $el.data('geometry-type') || 'point';
  const geometryRaw = $el.attr('data-geometry') || '';
  const categoryColor = $el.data('category-color') || '#e82c71';


  const map = L.map('mapRenderLocation', {
      scrollWheelZoom: false,
      attributionControl: true,
  });

  map.attributionControl.setPrefix(false);

  // Set map style from the centralized registry.
  window.OUMMapStyles.addBaseLayer(map, mapStyle, {
    mapboxKey: oum_tile_provider_mapbox_key || window.oum_tile_provider_mapbox_key || '',
    cartoKey: oum_tile_provider_carto_key || window.oum_tile_provider_carto_key || '',
    setupCustomImageLayer: setupCustomImageLayer,
    customImageHideTiles: window.oum_custom_image_hide_tiles,
    customImageBackgroundColor: window.oum_custom_image_background_color,
  });

  //define marker

  // Marker Icon
  let markerIcon = L.icon({
    iconUrl: marker_icon_url,
    iconSize: [26, 41],
    iconAnchor: [13, 41],
    popupAnchor: [0, -25],
    shadowUrl: marker_shadow_url,
    shadowSize: [41, 41],
    shadowAnchor: [13, 41]
  });

  function geoJsonCoordinatesToLatLngs(geometry) {
    const coordinates = geometry.type === 'Polygon' && Array.isArray(geometry.coordinates) && Array.isArray(geometry.coordinates[0])
      ? geometry.coordinates[0]
      : geometry.coordinates;

    if (!Array.isArray(coordinates)) {
      return [];
    }

    return coordinates
      .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
      .map((coordinate) => [coordinate[1], coordinate[0]]);
  }

  function renderVectorLocation() {
    if (geometryType !== 'polyline' && geometryType !== 'polygon') {
      return false;
    }

    if (!geometryRaw) {
      return false;
    }

    try {
      const geometry = JSON.parse(geometryRaw);
      const latLngs = geoJsonCoordinatesToLatLngs(geometry);

      if (latLngs.length < 2 || (geometryType === 'polygon' && latLngs.length < 3)) {
        return false;
      }

      const vectorOptions = {
        color: categoryColor,
        weight: 4,
        opacity: 0.9,
      };

      const vectorLayer = geometryType === 'polygon'
        ? L.polygon(latLngs, { ...vectorOptions, fillColor: categoryColor, fillOpacity: 0.25 })
        : L.polyline(latLngs, vectorOptions);

      vectorLayer.addTo(map);
      map.fitBounds(vectorLayer.getBounds(), { padding: [20, 20] });

      return true;
    } catch (error) {
      console.warn('Open User Map: Invalid single location geometry.', error);
      return false;
    }
  }
  
  if(renderVectorLocation()) {
      markerIsVisible = true;
  }else if(lat && lng) {
      //location has coordinates
      let locationMarker = L.marker([lat, lng], {icon: markerIcon}, {
          'draggable': false
      });

      map.setView([lat, lng], zoom);
      locationMarker.addTo(map);
      markerIsVisible = true;
  }else{
      //location has NO coordinates yet
      map.setView([0, 0], 1);
  }

  // Helper function to setup custom image layer
  function setupCustomImageLayer() {
    // Check if we have an image URL and bounds
    if (typeof window.oum_custom_image_url !== 'undefined' && window.oum_custom_image_url && 
        typeof window.oum_custom_image_bounds !== 'undefined' && window.oum_custom_image_bounds) {
      
      // Check if the uploaded file is an SVG
      const isSVG = window.oum_custom_image_url.toLowerCase().includes('.svg');
      
      if (isSVG) {
        // Handle SVG file - fetch and render as DOM elements
        setupSVGFromFile();
      } else {
        // Handle regular image file
        setupImageOverlay();
      }
    } else {
    }
  }

  // Helper function to setup SVG from uploaded file
  function setupSVGFromFile() {
    try {
    // Get bounds data (now properly handled as object)
    const bounds = window.oum_custom_image_bounds;

    // Validate bounds
    if (!bounds || typeof bounds.north === 'undefined' || typeof bounds.south === 'undefined' ||
        typeof bounds.east === 'undefined' || typeof bounds.west === 'undefined' ||
        bounds.north === '' || bounds.south === '' || bounds.east === '' || bounds.west === '') {
      console.warn('Open User Map: Invalid or empty bounds data, skipping SVG file layer');
      return;
    }


      // Fetch the SVG file and render it
      fetch(window.oum_custom_image_url)
        .then(response => response.text())
        .then(svgText => {
          // Create SVG element from the fetched content
          const svgElement = createSVGElement(svgText);
          if (!svgElement) {
            console.warn('Open User Map: Cannot create SVG layer from file - invalid SVG element');
            return;
          }

          // Create a custom SVG layer
          const svgLayer = L.svgOverlay(svgElement, [
            [bounds.north, bounds.west], // Southwest corner
            [bounds.south, bounds.east]  // Northeast corner
          ], {
            opacity: 1.0,
            interactive: true
          });

          svgLayer.addTo(map);


          // Store reference for potential removal
          window.oumCustomSVGLayer = svgLayer;

          console.log('Open User Map: Custom SVG file layer added successfully');
        })
        .catch(error => {
          console.warn('Open User Map: Error fetching SVG file:', error);
        });

    } catch (error) {
      console.warn('Open User Map: Error setting up custom SVG file layer:', error);
    }
  }

  // Helper function to setup regular image overlay
  function setupImageOverlay() {
    try {
    // Get bounds data (now properly handled as object)
    const bounds = window.oum_custom_image_bounds;

    // Validate bounds
    if (!bounds || typeof bounds.north === 'undefined' || typeof bounds.south === 'undefined' ||
        typeof bounds.east === 'undefined' || typeof bounds.west === 'undefined' ||
        bounds.north === '' || bounds.south === '' || bounds.east === '' || bounds.west === '') {
      console.warn('Open User Map: Invalid or empty bounds data, skipping image layer');
      return;
    }


      // Create image overlay
      const imageOverlay = L.imageOverlay(window.oum_custom_image_url, [
        [bounds.north, bounds.west], // Southwest corner
        [bounds.south, bounds.east]  // Northeast corner
      ], {
        opacity: 1.0,
        interactive: true
      });

      imageOverlay.addTo(map);


      // Store reference for potential removal
      window.oumCustomImageLayer = imageOverlay;

      console.log('Open User Map: Custom image layer added successfully');

    } catch (error) {
      console.warn('Open User Map: Error setting up custom image layer:', error);
    }
  }

  // Helper function to create SVG element from text
  function createSVGElement(svgText) {
    // Create a temporary div to parse the SVG
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = svgText;
    const svgElement = tempDiv.querySelector('svg');
    
    if (!svgElement) {
      console.warn('Open User Map: No valid SVG element found in SVG text');
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
    
    console.log('Open User Map: SVG element created successfully:', svgElement);
    
    return svgElement;
  }

})();