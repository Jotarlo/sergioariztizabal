window.addEventListener('load', function(e) {

  // Initialize image preview grid if editing a location
  const existingImages = jQuery('#oum_location_image').val();
  if (existingImages) {
    const imageUrls = existingImages.split('|');
    updateImagePreview(imageUrls);
  }

  // Restore the extended L object (OUMLeaflet.L) to the global scope (prevents conflicts with other Leaflet instances)
  window.L = window.OUMLeaflet.L;

  // FUNCTIONS

  //set lat & lng input fields
  function setLocationLatLng(markerLatLng, address, skipReverseGeocode = false) {
    jQuery('#oum_location_lat').val(markerLatLng.lat);
    jQuery('#oum_location_lng').val(markerLatLng.lng);
    
    // Only perform reverse geocoding if both subtitle field and autofill are enabled
    if (!skipReverseGeocode && shouldPerformReverseGeocoding()) {
      reverseGeocode(markerLatLng.lat, markerLatLng.lng, address);
    }
  }

  /**
   * Validates coordinates
   * @param {number|string} lat - Latitude
   * @param {number|string} lng - Longitude
   * @returns {boolean}
   */
  function validateCoordinates(lat, lng) {
    const parsedLat = parseFloat(lat);
    const parsedLng = parseFloat(lng);
    return (
      !isNaN(parsedLat) &&
      !isNaN(parsedLng) &&
      parsedLat >= -90 &&
      parsedLat <= 90 &&
      parsedLng >= -180 &&
      parsedLng <= 180
    );
  }

  /**
   * Check if reverse geocoding should be performed
   * Requires both subtitle field and autofill to be enabled
   * @returns {boolean}
   */
  function shouldPerformReverseGeocoding() {
    // Check if subtitle field is enabled
    const subtitleEnabled = typeof oum_enable_address !== 'undefined' && oum_enable_address === 'on';
    
    // Check if autofill is enabled
    const autofillEnabled = typeof oum_enable_address_autofill !== 'undefined' && oum_enable_address_autofill === 'on';
    
    return subtitleEnabled && autofillEnabled;
  }

  /**
   * Format address from Nominatim API response
   * @param {Object} addressData - Address data from Nominatim API
   * @param {string} displayName - Fallback display name
   * @returns {string}
   */
  function formatAddressFromResponse(addressData, displayName) {
    const addressParts = [];
    
    // Build address from most specific to least specific
    if (addressData.house_number) addressParts.push(addressData.house_number);
    if (addressData.road) addressParts.push(addressData.road);
    if (addressData.suburb) addressParts.push(addressData.suburb);
    if (addressData.city || addressData.town || addressData.village) {
      addressParts.push(addressData.city || addressData.town || addressData.village);
    }
    if (addressData.postcode) addressParts.push(addressData.postcode);
    if (addressData.country) addressParts.push(addressData.country);
    
    return addressParts.length > 0 
      ? addressParts.join(', ') 
      : displayName || 'Address not found';
  }

  /**
   * Reverse geocode coordinates to get address
   * Uses OpenStreetMap Nominatim API (free, no API key required)
   * @param {number} lat - Latitude
   * @param {number} lng - Longitude
   * @param {string} [providedAddress] - Optional address to use directly (e.g., from geosearch result)
   */
  function reverseGeocode(lat, lng, providedAddress) {
    // If address is already provided (e.g., from geosearch), use it directly
    if (providedAddress && providedAddress.trim()) {
      handleAddressResult(providedAddress.trim());
      return;
    }

    // Validate coordinates
    if (!validateCoordinates(lat, lng)) {
      console.warn('Open User Map: Invalid coordinates for reverse geocoding');
      return;
    }

    // Use Nominatim reverse geocoding API
    const url = `https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`;
    
    fetch(url, {
      headers: {
        'User-Agent': 'OpenUserMap/1.0' // Required by Nominatim
      }
    })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data && data.address) {
          const address = formatAddressFromResponse(data.address, data.display_name);
          handleAddressResult(address);
        } else {
          console.log('Open User Map: No address found for coordinates:', lat, lng);
        }
      })
      .catch(error => {
        console.warn('Open User Map: Reverse geocoding error:', error);
      });
  }

  /**
   * Handle the address result - log it and optionally fill subtitle field
   * @param {string} address - The formatted address
   */
  function handleAddressResult(address) {
    if (!address || typeof address !== 'string') {
      return;
    }

    console.log('Open User Map: Address:', address);
    
    // Auto-fill subtitle field if enabled (always update when address changes)
    if (shouldPerformReverseGeocoding()) {
      const subtitleField = jQuery('#oum_location_address');
      if (subtitleField.length) {
        subtitleField.val(address);
      }
    }
  }

  //set zoom level
  function setLocationZoom(zoomLevel) {
    jQuery('#oum_location_zoom').val(zoomLevel);
  }

  //set address field
  function setAddress(label) {
    jQuery('#oum_location_address').val(label);
  }


  // VARIABLES

  const latLngInputs = jQuery('#latLngInputs');
  const showLatLngInputs = jQuery('#showLatLngInputs');
  const initialGeometryValue = jQuery('#oum_location_geometry').val();
  let markerIsVisible = false;
  let currentGeometryType = ['polyline', 'polygon'].includes(jQuery('#oum_geometry_type').val()) ? jQuery('#oum_geometry_type').val() : 'point';
  let drawnItems = null;
  let vectorLayer = null;
  let drawControl = null;
  const initialGeometry = parseGeometryValue(initialGeometryValue);
  const preservedTypeState = {
    point: {
      lat: parseFloat(jQuery('#oum_location_lat').val()),
      lng: parseFloat(jQuery('#oum_location_lng').val()),
      zoom: parseInt(jQuery('#oum_location_zoom').val())
    },
    polyline: initialGeometry && initialGeometry.type === 'LineString' ? initialGeometry : null,
    polygon: initialGeometry && initialGeometry.type === 'Polygon' ? initialGeometry : null
  };

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


  // SETUP MAP

  const map = L.map('mapGetLocation', {
      scrollWheelZoom: false,
      zoomSnap: 1,
      zoomDelta: 1,
  });

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

  let locationMarker = L.marker([lat, lng], {icon: markerIcon}, {
      'draggable': true
  });
  
  // render map
  if(currentGeometryType === 'point' && lat && lng) {
      //location has coordinates
      map.setView([lat, lng], zoom);
      locationMarker.addTo(map);
      markerIsVisible = true;
  }else if(lat && lng) {
      map.setView([lat, lng], zoom);
  }else{
      //location has NO coordinates yet
      map.setView([0, 0], 1);
  }

  // Control: search address
  const search = new GeoSearch.GeoSearchControl({
    style: 'bar',
    showMarker: false,
    provider: oum_geosearch_selected_provider,
    searchLabel: oum_searchaddress_label
  });
  map.addControl(search);

  // Control: get current location
  if(enableCurrentLocation) {
    L.control.locate({
      flyTo: true,
      initialZoomLevel: 12,
      drawCircle: false,
      drawMarker: false
    }).addTo(map);
  }

  function getPolylineStyle() {
    return {
      color: '#e82c71',
      weight: 4,
      opacity: 0.9
    };
  }

  function getPolygonStyle() {
    return {
      ...getPolylineStyle(),
      fillColor: '#e82c71',
      fillOpacity: 0.25
    };
  }

  function createDrawControl() {
    if (!L.Control || !L.Control.Draw) {
      return;
    }

    if (drawControl && drawControl._map) {
      map.removeControl(drawControl);
    }

    drawControl = new L.Control.Draw({
      draw: {
        polyline: currentGeometryType === 'polyline' ? {
          shapeOptions: getPolylineStyle()
        } : false,
        polygon: currentGeometryType === 'polygon' ? {
          shapeOptions: getPolygonStyle()
        } : false,
        rectangle: false,
        circle: false,
        circlemarker: false,
        marker: false
      },
      edit: {
        featureGroup: drawnItems,
        remove: true
      }
    });
  }

  function addDrawControl() {
    if (drawControl && !drawControl._map) {
      map.addControl(drawControl);
    }
  }

  function removeDrawControl() {
    if (drawControl && drawControl._map) {
      map.removeControl(drawControl);
    }
  }

  function cancelActiveDrawTool() {
    const cancelButton = map.getContainer().querySelector(".leaflet-draw-actions a[title='Cancel drawing'], .leaflet-draw-actions a[title='Cancel editing'], .leaflet-draw-actions a[title='Cancel']");
    if (cancelButton) {
      cancelButton.click();
    }
  }

  function fitVectorBounds() {
    if (!vectorLayer || !vectorLayer.getBounds) {
      return;
    }

    const bounds = vectorLayer.getBounds();
    if (bounds && bounds.isValid()) {
      map.fitBounds(bounds, { padding: [20, 20] });
    }
  }

  function clearVectorLayer() {
    if (drawnItems) {
      drawnItems.clearLayers();
    }
    vectorLayer = null;
  }

  function parseGeometryValue(value) {
    if (!value || !value.toString().trim()) {
      return null;
    }

    try {
      const geometry = JSON.parse(value);
      return geometry && (geometry.type === 'LineString' || geometry.type === 'Polygon') ? geometry : null;
    } catch (error) {
      return null;
    }
  }

  function getGeometryTypeFromGeometry(geometry) {
    if (!geometry) {
      return '';
    }

    return geometry.type === 'Polygon' ? 'polygon' : (geometry.type === 'LineString' ? 'polyline' : '');
  }

  function rememberCurrentTypeState() {
    if (currentGeometryType === 'point') {
      preservedTypeState.point = {
        lat: parseFloat(jQuery('#oum_location_lat').val()),
        lng: parseFloat(jQuery('#oum_location_lng').val()),
        zoom: parseInt(jQuery('#oum_location_zoom').val())
      };
      return false;
    }

    if (vectorLayer && vectorLayer.toGeoJSON) {
      const geometry = vectorLayer.toGeoJSON().geometry;
      const geometryType = getGeometryTypeFromGeometry(geometry);
      if (geometryType) {
        preservedTypeState[geometryType] = geometry;
        return true;
      }
    }

    const geometryField = document.getElementById('oum_location_geometry');
    const fieldGeometry = geometryField ? parseGeometryValue(geometryField.value) : null;
    const fieldGeometryType = getGeometryTypeFromGeometry(fieldGeometry);
    if (fieldGeometryType) {
      preservedTypeState[fieldGeometryType] = fieldGeometry;
      return true;
    }

    return false;
  }

  function restorePointState() {
    const pointState = preservedTypeState.point || {};
    const hasValidPoint = !isNaN(pointState.lat) && !isNaN(pointState.lng);

    if (!hasValidPoint) {
      return;
    }

    jQuery('#oum_location_lat').val(pointState.lat);
    jQuery('#oum_location_lng').val(pointState.lng);

    if (!isNaN(pointState.zoom)) {
      jQuery('#oum_location_zoom').val(pointState.zoom);
    }

    locationMarker.setLatLng([pointState.lat, pointState.lng]);
    if (!markerIsVisible) {
      locationMarker.addTo(map);
      markerIsVisible = true;
    }

    if (!isNaN(pointState.zoom)) {
      map.setView([pointState.lat, pointState.lng], pointState.zoom);
    }
  }

  function restorePreservedGeometryForCurrentType() {
    const preservedGeometry = preservedTypeState[currentGeometryType];

    if (!preservedGeometry) {
      return false;
    }

    const rawCoordinates = preservedGeometry.type === 'Polygon' && Array.isArray(preservedGeometry.coordinates[0])
      ? preservedGeometry.coordinates[0]
      : preservedGeometry.coordinates;
    const latLngs = Array.isArray(rawCoordinates)
      ? rawCoordinates
        .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
        .map((coordinate) => L.latLng(Number(coordinate[1]), Number(coordinate[0])))
        .filter((latLng) => isFinite(latLng.lat) && isFinite(latLng.lng))
      : [];

    if (latLngs.length < 2 || (preservedGeometry.type === 'Polygon' && latLngs.length < 4)) {
      return false;
    }

    setVectorLayer(preservedGeometry.type === 'Polygon' ? L.polygon(latLngs, getPolygonStyle()) : L.polyline(latLngs, getPolylineStyle()));
    fitVectorBounds();
    return true;
  }

  function setVectorLayer(layer) {
    if (!drawnItems || !layer) {
      return;
    }

    clearVectorLayer();
    vectorLayer = layer;
    if (vectorLayer.setStyle) {
      vectorLayer.setStyle(currentGeometryType === 'polygon' ? getPolygonStyle() : getPolylineStyle());
    }
    drawnItems.addLayer(vectorLayer);
    syncGeometryFields();
  }

  function syncGeometryFields() {
    const geometryField = document.getElementById('oum_location_geometry');
    if (!geometryField) {
      return;
    }

    if (currentGeometryType === 'point' || !vectorLayer) {
      geometryField.value = '';
      return;
    }

    const geometry = vectorLayer.toGeoJSON().geometry;
    geometryField.value = JSON.stringify(geometry);
    const geometryType = getGeometryTypeFromGeometry(geometry);
    if (geometryType) {
      preservedTypeState[geometryType] = geometry;
    }

    const bounds = vectorLayer.getBounds();
    if (bounds && bounds.isValid()) {
      setLocationLatLng(bounds.getCenter(), '', true);
      setLocationZoom(map.getZoom());
    }
  }

  function setGeometryType(type) {
    rememberCurrentTypeState();

    currentGeometryType = type === 'polyline' || type === 'polygon' ? type : 'point';

    cancelActiveDrawTool();
    jQuery('#oum_geometry_type').val(currentGeometryType);

    document.querySelectorAll('[data-oum-location-type-card]').forEach((card) => {
      const isSelected = card.getAttribute('data-oum-location-type-card') === currentGeometryType;
      card.classList.toggle('is-selected', isSelected);
      const input = card.querySelector('input[type="radio"]');
      if (input) {
        input.checked = isSelected;
      }
    });

    const help = document.querySelector('[data-oum-backend-location-type-help]');
    if (help) {
      help.textContent = currentGeometryType === 'point'
        ? (help.dataset.pointHelp || '')
        : (help.dataset.vectorHelp || '');
    }

    updateManualInputVisibility();

    if (currentGeometryType === 'point') {
      removeDrawControl();
      clearVectorLayer();
      restorePointState();
    } else {
      if (markerIsVisible) {
        map.removeLayer(locationMarker);
        markerIsVisible = false;
      }
      createDrawControl();
      addDrawControl();
      if (vectorLayer && getGeometryTypeFromGeometry(vectorLayer.toGeoJSON().geometry) !== currentGeometryType) {
        clearVectorLayer();
      }
      if (!vectorLayer) {
        restorePreservedGeometryForCurrentType();
      }
    }

    syncGeometryFields();
  }

  function updateManualInputVisibility() {
    const showGeometry = currentGeometryType === 'polyline' || currentGeometryType === 'polygon';
    jQuery('.oum-latlng-fields').toggle(!showGeometry);
    jQuery('.oum-geometry-fields').toggle(showGeometry);
  }

  function setVectorGeometry(geometry) {
    if (!geometry || !Array.isArray(geometry.coordinates) || (geometry.type !== 'LineString' && geometry.type !== 'Polygon')) {
      clearVectorLayer();
      syncGeometryFields();
      return;
    }

    const rawCoordinates = geometry.type === 'Polygon' && Array.isArray(geometry.coordinates[0])
      ? geometry.coordinates[0]
      : geometry.coordinates;
    const latLngs = rawCoordinates
      .filter((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2)
      .map((coordinate) => L.latLng(Number(coordinate[1]), Number(coordinate[0])))
      .filter((latLng) => isFinite(latLng.lat) && isFinite(latLng.lng));

    if (latLngs.length < 2 || (geometry.type === 'Polygon' && latLngs.length < 4)) {
      clearVectorLayer();
      syncGeometryFields();
      return;
    }

    setGeometryType(geometry.type === 'Polygon' ? 'polygon' : 'polyline');
    setVectorLayer(geometry.type === 'Polygon' ? L.polygon(latLngs, getPolygonStyle()) : L.polyline(latLngs, getPolylineStyle()));
    fitVectorBounds();

  }

  function setupDrawing() {
    if (!L.Control || !L.Control.Draw) {
      return;
    }

    drawnItems = L.featureGroup().addTo(map);
    createDrawControl();

    map.on(L.Draw.Event.CREATED, function(event) {
      if (currentGeometryType === 'point') {
        return;
      }
      setVectorLayer(event.layer);
    });

    map.on(L.Draw.Event.EDITED, syncGeometryFields);

    map.on(L.Draw.Event.DELETED, function() {
      vectorLayer = null;
      preservedTypeState[currentGeometryType] = null;
      syncGeometryFields();
    });

    document.querySelectorAll('input[name="oum_backend_geometry_type_choice"]').forEach((input) => {
      input.addEventListener('change', function() {
        setGeometryType(this.value);
      });
    });

    const geometryField = document.getElementById('oum_location_geometry');
    if (geometryField) {
      geometryField.addEventListener('change', function() {
        if (!this.value.trim()) {
          preservedTypeState[currentGeometryType] = null;
          clearVectorLayer();
          syncGeometryFields();
          return;
        }

        try {
          setVectorGeometry(JSON.parse(this.value));
        } catch (error) {
          console.warn('Open User Map: Invalid geometry JSON.', error);
        }
      });
    }
  }

  setupDrawing();
  setGeometryType(currentGeometryType);

  if (currentGeometryType !== 'point' && initialGeometryValue) {
    try {
      setVectorGeometry(JSON.parse(initialGeometryValue));
    } catch (error) {
      console.warn('Open User Map: Invalid saved geometry JSON.', error);
    }
  }


  // Trigger resize (sometimes necessary to render the map properly)
  setInterval(function () {
    map.invalidateSize();
  }, 1000)


  // EVENTS

  //Event: click on map to set marker
  map.on('click locationfound', function(e) {
    if (currentGeometryType !== 'point') {
      return;
    }

    let coords = e.latlng;

    locationMarker.setLatLng(coords);

    if(!markerIsVisible) {
        locationMarker.addTo(map);
        markerIsVisible = true;
    }

    setLocationLatLng(coords);
    setLocationZoom(map.getZoom());
  });

  //Event: map zoom change
  map.on('zoomend', function(e) {
    setLocationZoom(map.getZoom());
  });

  //Event: geosearch success
  map.on('geosearch/showlocation', function(e) {
    if (currentGeometryType !== 'point') {
      return;
    }

    let coords = e.marker._latlng;
    let label = e.location.label;

    // Set coordinates and address immediately (before map animation) for instant feedback
    setLocationLatLng(coords, label);
    
    locationMarker.setLatLng(coords);

    if (!markerIsVisible) {
      locationMarker.addTo(map);
      markerIsVisible = true;
    }

    // Update zoom level after map finishes flying to location
    map.once('zoomend', function() {
      setLocationZoom(map.getZoom());
    });
  });

  //Event: drag marker
  locationMarker.on('dragend', function(e) {
      setLocationLatLng(e.target.getLatLng());
  });

  //Event: click on "edit coordinates manually"
  showLatLngInputs.on('click', function(e) {
      e.preventDefault();
      jQuery(this).parent('.hint').hide();
      latLngInputs.fadeIn();
  });

  // Event: Update map when coordinates or zoom change
  jQuery('#oum_location_lat, #oum_location_lng, #oum_location_zoom').on('change', function() {
    const lat = parseFloat(jQuery('#oum_location_lat').val());
    const lng = parseFloat(jQuery('#oum_location_lng').val());
    const zoom = parseInt(jQuery('#oum_location_zoom').val());

    // Only update if we have valid coordinates
    if (currentGeometryType === 'point' && !isNaN(lat) && !isNaN(lng)) {
      // Update marker position
      locationMarker.setLatLng([lat, lng]);
      if (!markerIsVisible) {
        locationMarker.addTo(map);
        markerIsVisible = true;
      }

      // Update map view
      map.setView([lat, lng], zoom);
    }
  });

  // Media Uploader
  jQuery('#oum_location_image_preview').closest('form').on('click', '.oum_upload_image_button', function(e) {
    e.preventDefault();

    // Create new media frame
    const image_uploader = wp.media({
      title: 'Custom image',
      multiple: true,
      library: {
        type: 'image'
      },
      button: {
        text: 'Use these images'
      }
    });

    // Bind to select event
    image_uploader.on('select', function() {
      const attachments = image_uploader.state().get('selection').toJSON();
      const maxImages = 5;
      const existingImages = jQuery('#oum_location_image_preview img').length;
      const remainingSlots = maxImages - existingImages;
      
      if (attachments.length > remainingSlots) {
        alert('Maximum ' + maxImages + ' images allowed. Only the first ' + remainingSlots + ' images will be added.');
      }

      const imagesToProcess = attachments.slice(0, remainingSlots);
      let existingUrls = jQuery('#oum_location_image').val() ? jQuery('#oum_location_image').val().split('|') : [];
      
      imagesToProcess.forEach(attachment => {
        const url = attachment.sizes.large ? attachment.sizes.large.url : attachment.sizes.full.url;
        existingUrls.push(url);
      });

      jQuery('#oum_location_image').val(existingUrls.join('|'));
      updateImagePreview(existingUrls);
    });

    image_uploader.open();
    return false;
  });

  // Build a preview item with DOM APIs so image URLs cannot inject HTML attributes.
  function createImagePreviewItem(url) {
    const previewItem = document.createElement('div');
    previewItem.className = 'image-preview-item';
    previewItem.draggable = true;
    previewItem.setAttribute('data-url', url);

    const img = document.createElement('img');
    img.setAttribute('alt', 'Preview');
    img.src = url;

    const remove = document.createElement('div');
    remove.className = 'remove-image';
    remove.setAttribute('title', 'Remove image');
    remove.textContent = '\u00d7';

    const handle = document.createElement('div');
    handle.className = 'drag-handle';
    handle.setAttribute('title', 'Drag to reorder');
    handle.textContent = '\u22ee\u22ee';

    previewItem.appendChild(img);
    previewItem.appendChild(remove);
    previewItem.appendChild(handle);

    return previewItem;
  }

  // Function to update image preview
  function updateImagePreview(imageUrls) {
    const previewContainer = jQuery('#oum_location_image_preview');
    
    // Remove old preview and classes
    previewContainer.empty().removeClass('has-image');
    
    if (!imageUrls || imageUrls.length === 0) {
      return;
    }

    previewContainer.addClass('has-image');

    // Create preview grid
    const gridContainer = jQuery('<div class="image-preview-grid"></div>');
    
    imageUrls.forEach((url) => {
      if (!url) return; // Skip empty URLs
      
      const item = createImagePreviewItem(url);
      setupDragAndDrop(item);
      gridContainer.append(item);
    });
    
    previewContainer.append(gridContainer);
  }

  // Variables for drag and drop
  let isDragging = false;
  let placeholder;

  function setupDragAndDrop(previewItem) {
    previewItem.addEventListener('mousedown', function(e) {
      if (e.target.classList.contains('remove-image')) return;

      isDragging = true;
      this.classList.add('dragging');

      // Get element dimensions once at start
      const rect = this.getBoundingClientRect();
      this.style.width = rect.width + 'px';
      this.style.height = rect.height + 'px';

      // Create placeholder immediately
      createPlaceholder(this);
      
      // Set up dragged element
      this.style.position = 'fixed';
      this.style.zIndex = '1000';
      this.style.opacity = '0.9';
      this.style.transform = 'scale(1.05) rotate(1deg)';
      this.style.pointerEvents = 'none';
      this.style.boxShadow = '0 5px 15px rgba(0,0,0,0.15)';

      // Set initial position
      moveDraggedElement(this, e);

      document.body.style.cursor = 'grabbing';

      // Store initial grid container for safety check
      this.initialContainer = this.closest('.image-preview-grid');
    });
    
    previewItem.addEventListener('touchstart', handleTouchStart);
  }

  function createPlaceholder(element) {
    placeholder = document.createElement("div");
    placeholder.classList.add("image-preview-placeholder");
    placeholder.style.width = element.offsetWidth + "px";
    placeholder.style.height = element.offsetHeight + "px";
    placeholder.style.transition = "transform 0.2s ease";
    placeholder.style.border = "2px dashed #e82c71";
    placeholder.style.borderRadius = "4px";
    placeholder.style.backgroundColor = "rgba(224, 42, 175, 0.05)";
    element.parentNode.insertBefore(placeholder, element);
  }

  function moveDraggedElement(draggable, e) {
    const rect = draggable.getBoundingClientRect();
    const centerOffsetX = rect.width / 2;
    const centerOffsetY = rect.height / 2;
    
    // Position element directly at cursor with center offset
    draggable.style.left = (e.clientX - centerOffsetX) + 'px';
    draggable.style.top = (e.clientY - centerOffsetY) + 'px';
  }

  function updatePlaceholderPosition(e) {
    const gridContainer = document.querySelector("#oum_location_image_preview .image-preview-grid");
    if (!gridContainer) return;

    const draggable = document.querySelector('.dragging');
    if (!draggable) return;

    const siblings = [...gridContainer.querySelectorAll(".image-preview-item:not(.dragging)")];
    
    // Find the closest sibling based on mouse position
    const closestSibling = siblings.reduce((closest, child) => {
        const rect = child.getBoundingClientRect();
        const centerX = rect.left + rect.width / 2;
        const offset = e.clientX - centerX;
        
        if (offset < 0 && (!closest.element || offset > closest.offset)) {
            return { offset: offset, element: child };
        }
        return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null });

    if (closestSibling.element) {
        gridContainer.insertBefore(placeholder, closestSibling.element);
    } else {
        gridContainer.appendChild(placeholder);
    }
  }

  function handleDragMove(e) {
    if (!isDragging) return;

    const draggable = document.querySelector(".dragging");
    if (!draggable) return;

    // Update dragged element position
    moveDraggedElement(draggable, e);
    
    // Check if cursor is still within any grid container
    const gridContainer = document.querySelector("#oum_location_image_preview .image-preview-grid");
    if (!gridContainer) return;

    const gridRect = gridContainer.getBoundingClientRect();
    const isWithinGrid = e.clientX >= gridRect.left - 50 && 
                        e.clientX <= gridRect.right + 50 && 
                        e.clientY >= gridRect.top - 50 && 
                        e.clientY <= gridRect.bottom + 50;

    // If cursor is outside grid boundaries, hide placeholder
    if (!isWithinGrid && placeholder) {
      placeholder.style.display = 'none';
    } else if (placeholder) {
      placeholder.style.display = 'block';
      updatePlaceholderPosition(e);
    }
  }

  function handleDragEnd() {
    const draggable = document.querySelector(".dragging");
    if (!draggable) return;

    // Reset cursor
    document.body.style.cursor = "";

    // Check if we're still within the grid
    const gridContainer = document.querySelector("#oum_location_image_preview .image-preview-grid");
    if (!gridContainer) {
      // If no grid found, return item to its initial position
      if (draggable.initialContainer) {
        draggable.initialContainer.appendChild(draggable);
      }
    } else {
      // Place draggable element at placeholder position if within grid
      if (placeholder && placeholder.style.display !== 'none') {
        draggable.style.transition = "none";
        placeholder.parentNode.insertBefore(draggable, placeholder);
      } else {
        // If placeholder is hidden (outside grid), append to end
        gridContainer.appendChild(draggable);
      }
    }

    // Remove placeholder
    if (placeholder) {
      placeholder.remove();
    }

    // Reset draggable element styles
    draggable.style.position = "";
    draggable.style.zIndex = "";
    draggable.style.top = "";
    draggable.style.left = "";
    draggable.style.width = "";
    draggable.style.height = "";
    draggable.style.transform = "";
    draggable.style.pointerEvents = "";
    draggable.style.boxShadow = "";
    draggable.classList.remove("dragging");

    isDragging = false;

    // Update image order
    updateImageOrder();
  }

  function handleTouchStart(e) {
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent("mousedown", {
      clientX: touch.clientX,
      clientY: touch.clientY
    });
    this.dispatchEvent(mouseEvent);
  }

  // Add document-level event listeners for drag and drop
  document.addEventListener("mousemove", handleDragMove);
  document.addEventListener("mouseup", handleDragEnd);

  // Remove image handler with animation
  jQuery('body').on('click', '.remove-image', function(e) {
    e.preventDefault();
    const item = jQuery(this).closest('.image-preview-item');
    const url = item.data('url');
    
    // Remove from hidden input
    const currentUrls = jQuery('#oum_location_image').val().split('|');
    const newUrls = currentUrls.filter(currentUrl => currentUrl !== url);
    jQuery('#oum_location_image').val(newUrls.join('|'));
    
    // Animate and remove preview item
    item.css({
      transition: 'all 0.3s ease',
      transform: 'scale(0.8)',
      opacity: '0'
    });
    
    setTimeout(() => {
      item.remove();
      
      // Remove has-image class if no images left
      if (newUrls.length === 0) {
        jQuery('#oum_location_image_preview').removeClass('has-image').empty();
      }
    }, 300);
  });

  // Add back the updateImageOrder function
  function updateImageOrder() {
    const imageUrls = [];
    jQuery('#oum_location_image_preview .image-preview-item').each(function() {
      imageUrls.push(jQuery(this).data('url'));
    });
    jQuery('#oum_location_image').val(imageUrls.join('|'));
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

}, false);