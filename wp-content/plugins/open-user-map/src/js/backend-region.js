(function() {

  // Restore the extended L object (OUMLeaflet.L) to the global scope (prevents conflicts with other Leaflet instances)
  window.L = window.OUMLeaflet.L;

  const map = L.map('mapGetRegion', {
    scrollWheelZoom: false,
    touchZoom: true,
    zoomSnap: 0.1,
    zoomDelta: 0.1,
  });

  // Add coarse zoom controls as a quick alternative to precise 0.1 zoom steps.
  const FastZoomControl = L.Control.extend({
    options: {
      position: 'topleft'
    },
    onAdd: function() {
      const container = L.DomUtil.create('div', 'leaflet-bar oum-fast-zoom-control');

      const zoomInFast = L.DomUtil.create('a', '', container);
      zoomInFast.innerHTML = '++';
      zoomInFast.href = '#';
      zoomInFast.title = 'Zoom in faster';
      zoomInFast.style.fontWeight = 'bold';

      const zoomOutFast = L.DomUtil.create('a', '', container);
      zoomOutFast.innerHTML = '--';
      zoomOutFast.href = '#';
      zoomOutFast.title = 'Zoom out faster';
      zoomOutFast.style.fontWeight = 'bold';

      L.DomEvent.disableClickPropagation(container);
      L.DomEvent.on(zoomInFast, 'click', function(e) {
        L.DomEvent.preventDefault(e);
        map.setZoom(map.getZoom() + 1);
      });
      L.DomEvent.on(zoomOutFast, 'click', function(e) {
        L.DomEvent.preventDefault(e);
        map.setZoom(map.getZoom() - 1);
      });

      return container;
    }
  });
  map.addControl(new FastZoomControl());

  // prevent moving/zoom outside main world bounds
  let world_bounds = L.latLngBounds(L.latLng(-85, -200), L.latLng(85, 200));
  let world_min_zoom = map.getBoundsZoom(world_bounds);
  map.setMaxBounds(world_bounds);
  map.setMinZoom(Math.ceil(world_min_zoom));
  map.on('drag', function() {
    map.panInsideBounds(world_bounds, { animate: false });
  });

  // Set map style from the centralized registry.
  window.OUMMapStyles.addBaseLayer(map, mapStyle, {
    mapboxKey: window.oum_tile_provider_mapbox_key || '',
    cartoKey: window.oum_tile_provider_carto_key || '',
    setupCustomImageLayer: setupCustomImageLayer,
    customImageHideTiles: window.oum_custom_image_hide_tiles,
    customImageBackgroundColor: window.oum_custom_image_background_color,
  });

  // Geosearch Provider
  switch (oum_geosearch_provider) {
    case 'osm':
      oum_geosearch_selected_provider = new GeoSearch.OpenStreetMapProvider();
      break;
    case 'geoapify':
      oum_geosearch_selected_provider = new GeoSearch.GeoapifyProvider({
        params: {
          apiKey: oum_geosearch_provider_geoapify_key
        }
      });
      break;
    case 'here':
      oum_geosearch_selected_provider = new GeoSearch.HereProvider({
        params: {
          apiKey: oum_geosearch_provider_here_key
        }
      });
      break;
    case 'mapbox':
      oum_geosearch_selected_provider = new GeoSearch.MapBoxProvider({
        params: {
          access_token: oum_geosearch_provider_mapbox_key
        }
      });
      break;
    default:
      oum_geosearch_selected_provider = new GeoSearch.OpenStreetMapProvider();
      break;
  }

  const search = new GeoSearch.GeoSearchControl({
      style: 'bar',
      showMarker: false,
      provider: oum_geosearch_selected_provider,
      searchLabel: oum_searchaddress_label
  });
  map.addControl(search);

  map.setView([lat, lng], zoom);

  // set Initial view by move/zoom
  map.on('move', function(e) {
      setInitialLatLngZoom(map.getCenter(), map.getZoom());
  });

  //set lat & lng & zoom input fields
  function setInitialLatLngZoom(mapCenterLatLng, mapZoom) {
      jQuery('#oum_lat').val(mapCenterLatLng.lat);
      jQuery('#oum_lng').val(mapCenterLatLng.lng);
      jQuery('#oum_zoom').val(mapZoom);
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
