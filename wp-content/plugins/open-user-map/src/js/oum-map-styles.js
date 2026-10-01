(function(window) {
  'use strict';

  const openFreeMapAttribution = '<a href="https://openfreemap.org/" target="_blank" rel="noopener noreferrer">OpenFreeMap</a> &copy; <a href="https://openmaptiles.org/" target="_blank" rel="noopener noreferrer">OpenMapTiles</a> Data from <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a>';

  function getRegistry() {
    return window.oum_map_style_registry || {};
  }

  function getStyleKey(mapStyle) {
    const normalizedStyle = String(mapStyle || '').toLowerCase();

    return Object.keys(getRegistry()).find(function(styleKey) {
      return styleKey.toLowerCase() === normalizedStyle;
    }) || '';
  }

  function getStyleDefinition(mapStyle) {
    const styleKey = getStyleKey(mapStyle);

    if (styleKey) {
      return getRegistry()[styleKey];
    }

    if (String(mapStyle || '').toLowerCase() === 'customimage') {
      return {
        provider: 'custom_image'
      };
    }

    return getOpenFreeMapDefinition(mapStyle);
  }

  function getOpenFreeMapDefinition(mapStyle) {
    const styleSlug = String(mapStyle || '').replace(/^OpenFreeMap\./i, '').toLowerCase();
    const openFreeMapStyleUrls = {
      liberty: 'https://tiles.openfreemap.org/styles/liberty',
      bright: 'https://tiles.openfreemap.org/styles/bright',
      positron: 'https://tiles.openfreemap.org/styles/positron',
      dark: 'https://tiles.openfreemap.org/styles/dark',
      fiord: 'https://tiles.openfreemap.org/styles/fiord'
    };

    // Safety net for cached/optimized pages where localized registry data is missing.
    return openFreeMapStyleUrls[styleSlug]
      ? {
        provider: 'openfreemap',
        url: openFreeMapStyleUrls[styleSlug]
      }
      : null;
  }

  function ensureLeafletZoomBounds(map) {
    // MarkerCluster requires a finite Leaflet maxZoom; vector layers do not provide one.
    if (map && typeof map.getMaxZoom === 'function' && !isFinite(map.getMaxZoom()) && typeof map.setMaxZoom === 'function') {
      map.setMaxZoom(20);
    }
  }

  function getKey(options, key) {
    return options && options[key] ? options[key] : '';
  }

  function addCartoTileLayer(map, mapStyle, options) {
    const cartoKey = getKey(options, 'cartoKey');

    // Saved no-key CARTO styles are switched server-side before this code runs.
    if (!cartoKey) {
      console.warn('Open User Map: This CARTO style requires an API key.');
      return null;
    }

    return window.L.tileLayer.provider(mapStyle, {
      apikey: cartoKey
    }).addTo(map);
  }

  function addCustomCartoLayers(map, style, options) {
    const cartoKey = getKey(options, 'cartoKey');

    // Custom styles are built from direct CARTO tile URLs and need the key manually.
    if (!cartoKey) {
      console.warn('Open User Map: This CARTO style requires an API key.');
      return null;
    }

    const keyParam = '?key=' + encodeURIComponent(cartoKey);
    window.L.tileLayer(style.carto_base_url + keyParam).addTo(map);

    return window.L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png' + keyParam, {
      tileSize: 512,
      zoomOffset: -1
    }).addTo(map);
  }

  function addMapBoxLayer(map, style, options) {
    const mapboxKey = getKey(options, 'mapboxKey');

    // Saved no-key MapBox styles are switched server-side before this code runs.
    if (!mapboxKey) {
      console.warn('Open User Map: This MapBox style requires an API key.');
      return null;
    }

    return window.L.tileLayer.provider('MapBox', {
      id: style.mapbox_id,
      accessToken: mapboxKey
    }).addTo(map);
  }

  function addCustomImageLayer(map, options) {
    if (options && typeof options.setupCustomImageLayer === 'function') {
      options.setupCustomImageLayer();
    }

    if (options && options.customImageHideTiles) {
      const layer = window.L.tileLayer('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', {
        attribution: '',
        opacity: 0
      }).addTo(map);

      if (options.customImageBackgroundColor) {
        map.getContainer().style.backgroundColor = options.customImageBackgroundColor;
      }

      return layer;
    }

    return window.L.tileLayer.provider('OpenStreetMap.Mapnik').addTo(map);
  }

  function addOpenFreeMapLayer(map, style) {
    if (!window.L || typeof window.L.maplibreGL !== 'function') {
      console.warn('Open User Map: MapLibre assets are required for OpenFreeMap styles.');
      return window.L && window.L.tileLayer && window.L.tileLayer.provider
        ? window.L.tileLayer.provider('OpenStreetMap.Mapnik').addTo(map)
        : null;
    }

    ensureLeafletZoomBounds(map);

    return window.L.maplibreGL({
      style: style.url,
      attributionControl: {
        customAttribution: openFreeMapAttribution
      }
    }).addTo(map);
  }

  function addBaseLayer(map, mapStyle, options) {
    const style = getStyleDefinition(mapStyle) || {};

    if (style.provider === 'openfreemap') {
      return addOpenFreeMapLayer(map, style);
    }

    if (style.provider === 'custom_image') {
      return addCustomImageLayer(map, options);
    }

    if (style.mapbox_id) {
      return addMapBoxLayer(map, style, options);
    }

    if (style.carto_base_url) {
      return addCustomCartoLayers(map, style, options);
    }

    if (style.provider === 'carto') {
      return addCartoTileLayer(map, mapStyle, options);
    }

    return window.L.tileLayer.provider(mapStyle).addTo(map);
  }

  window.OUMMapStyles = {
    getStyleDefinition: getStyleDefinition,
    isOpenFreeMapStyle: function(mapStyle) {
      const style = getStyleDefinition(mapStyle);

      return !!style && style.provider === 'openfreemap';
    },
    addOpenFreeMapLayer: function(map, mapStyle) {
      const style = getStyleDefinition(mapStyle);

      return style && style.provider === 'openfreemap' ? addOpenFreeMapLayer(map, style) : null;
    },
    addBaseLayer: addBaseLayer
  };
})(window);
