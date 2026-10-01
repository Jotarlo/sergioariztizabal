window.addEventListener('load', function(e) {

  // Restore the extended L object (OUMLeaflet.L) to the global scope (prevents conflicts with other Leaflet instances)
  window.L = window.OUMLeaflet.L;

  const map = L.map('mapGetInitial', {
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

  // Tabs
  const tabs = document.querySelectorAll(".oum-nav-tab-wrapper > .nav-tab");
  const activeTabInput = document.getElementById("oum_active_tab");

  for(i = 0; i < tabs.length; i++) {
    tabs[i].addEventListener("click", switchTab);
  }

  function switchTab(event) {
    event.preventDefault();
    const activeTab = document.querySelector(".oum-nav-tab-wrapper > .nav-tab.nav-tab-active");
    const activePane = document.querySelector(".oum-tab-pane.active");
    if (activeTab) {
      activeTab.classList.remove("nav-tab-active");
    }
    if (activePane) {
      activePane.classList.remove("active");
    }

    let clickedTab = event.currentTarget;
    let anchor = event.currentTarget;
    let activePaneID = anchor.getAttribute("href");
    let tabId = activePaneID.replace('#', '');

    clickedTab.classList.add("nav-tab-active");
    const paneToActivate = document.querySelector(activePaneID);
    if (paneToActivate) {
      paneToActivate.classList.add("active");
    }

    // Update hidden input field to preserve active tab on form submission
    if (activeTabInput) {
      activeTabInput.value = tabId;
    }

    // Update URL parameter without page reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabId);
    window.history.pushState({}, '', url);

    //reposition map
    map.invalidateSize();
  }

  // Activate tab on page load based on URL parameter or hidden input value
  const urlParams = new URLSearchParams(window.location.search);
  const tabFromUrl = urlParams.get('tab');
  const tabFromInput = activeTabInput ? activeTabInput.value : null;
  const tabToActivate = tabFromUrl || tabFromInput || 'tab-1';
  
  if (tabToActivate && tabToActivate !== 'tab-1') {
    const tabToClick = document.querySelector(`.oum-nav-tab-wrapper > .nav-tab[href="#${tabToActivate}"]`);
    if (tabToClick) {
      // Remove active classes from default tab
      document.querySelector(".oum-nav-tab-wrapper > .nav-tab.nav-tab-active")?.classList.remove("nav-tab-active");
      document.querySelector(".oum-tab-pane.active")?.classList.remove("active");
      
      // Activate the correct tab
      tabToClick.classList.add("nav-tab-active");
      const paneToActivate = document.querySelector(`#${tabToActivate}`);
      if (paneToActivate) {
        paneToActivate.classList.add("active");
      }
      
      // Update hidden input
      if (activeTabInput) {
        activeTabInput.value = tabToActivate;
      }
      
      // Reposition map
      map.invalidateSize();
    }
  }

  // Settings: Community Contributions (progressive disclosure)
  if(jQuery('#oum_enable_add_location_toggle').length > 0) {
    toggleCommunityContributionSettings(jQuery('#oum_enable_add_location_toggle').is(':checked'));

    jQuery('#oum_enable_add_location_toggle').on('change', function() {
      toggleCommunityContributionSettings(this.checked);
    });

    function toggleCommunityContributionSettings(isEnabled) {
      if(isEnabled) {
        jQuery('.community-enabled-tip').show();
        jQuery('.community-disabled-tip').hide();
        // Hide visitor-only settings; submission form stays visible (.wrap-community-tab-submission-form).
        jQuery('.wrap-community-tab-gated').show();
        jQuery('.community-tab-disabled-message').hide();
      } else {
        jQuery('.community-enabled-tip').hide();
        jQuery('.community-disabled-tip').show();
        jQuery('.wrap-community-tab-gated').hide();
        jQuery('.community-tab-disabled-message').show();
      }
    }
  }

  // Location Submissions tab: smooth scroll for quick links
  jQuery(document).on('click', '.community-quick-links a[href^="#"]', function(e) {
    const id = this.getAttribute('href');
    if (id === '#') return;
    const target = document.querySelector(id);
    if (target) {
      e.preventDefault();
      target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  // map style selector
  jQuery('.map_styles input[type=radio]').on('change', function(e) {
    jQuery('.map_styles label').removeClass('checked');
    jQuery(this).parent('label').addClass('checked');
    toggleTileProviderApiKeySettings(e.target.value);
  });

  // API keys stay near their provider groups; selecting keyed styles still validates them.
  if(jQuery('.map_styles input[type=radio]').length > 0) {
    toggleTileProviderApiKeySettings(jQuery('.map_styles input[type=radio]:checked').val());

    jQuery('#oum_tile_provider_carto_key').on('input', function() {
      toggleTileProviderApiKeySettings(jQuery('.map_styles input[type=radio]:checked').val());
    });

    jQuery('#oum_tile_provider_mapbox_key').on('input', function() {
      toggleTileProviderApiKeySettings(jQuery('.map_styles input[type=radio]:checked').val());
    });

    function toggleTileProviderApiKeySettings(val) {

      jQuery('.wrap-custom-image-settings').hide();
      jQuery('.map_style_provider_notice').hide();
      const style = window.OUMMapStyles && window.OUMMapStyles.getStyleDefinition
        ? window.OUMMapStyles.getStyleDefinition(val)
        : null;
      const requiredApiKey = style && style.requires_api_key ? style.requires_api_key : '';

      if(requiredApiKey === 'mapbox') {
        // validate
        if(jQuery('#oum_tile_provider_mapbox_key').val() == '') {
          jQuery('.map_style_provider_notice-mapbox').show();
          alert("This MapBox style requires an API Key.");
          window.scrollTo({
            top: jQuery('#oum_tile_provider_mapbox_key').offset().top - 200, 
            behavior: 'smooth'
          });
        }
      } else if(requiredApiKey === 'carto') {
        // validate
        if(jQuery('#oum_tile_provider_carto_key').val() == '') {
          jQuery('.map_style_provider_notice-carto').show();
          alert("This CARTO style requires an API Key.");
          window.scrollTo({
            top: jQuery('#oum_tile_provider_carto_key').offset().top - 200, 
            behavior: 'smooth'
          });
        }
      } else if(val === 'CustomImage') {
        // show custom image settings
        jQuery('.wrap-custom-image-settings').show();

        // Leaflet needs a visible container before it can calculate tiles and bounds correctly.
        setTimeout(function() {
          if (typeof window.oumRefreshImagePositionEditor === 'function') {
            window.oumRefreshImagePositionEditor();
          }
          window.dispatchEvent(new Event('resize'));
        }, 100);
        
        // Only scroll to custom image settings if no image is currently set
        if (!window.oum_custom_image_url || window.oum_custom_image_url === '') {
          window.scrollTo({
            top: jQuery('.wrap-custom-image-settings').offset().top - 200, 
            behavior: 'smooth'
          });
        }
      }
    }
  }

  // marker icon selector
  jQuery('.marker_icons input[type=radio]').on('change', function(e) {
    jQuery('.marker_icons label').removeClass('checked');
    jQuery(this).parent('label').addClass('checked');
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
      searchLabel: oum_searchaddress_label,
  });
  map.addControl(search);

  map.setView([lat, lng], zoom);

  // set Initial view by move/zoom
  map.on('move', function(e) {
      setInitialLatLngZoom(map.getCenter(), map.getZoom());
  });

  //set lat & lng & zoom input fields
  function setInitialLatLngZoom(mapCenterLatLng, mapZoom) {
      jQuery('#oum_start_lat').val(mapCenterLatLng.lat);
      jQuery('#oum_start_lng').val(mapCenterLatLng.lng);
      jQuery('#oum_start_zoom').val(mapZoom);
  }

  //Custom Fields
  let maxField = 10; //Input fields increment limitation
  let addButton = jQuery('.oum_add_button'); //Add button selector
  let wrapper = jQuery('.oum_custom_fields_wrapper'); //Input field wrapper  
  let x = 1; //Initial field counter is 1
  
  //Once add button is clicked
  jQuery(addButton).click(function(e){
    e.preventDefault();
    
    //Check maximum number of input fields
    if(x < maxField){ 
        x++; //Increment field counter
        let index = Date.now();
        let fieldHTML = `
          <tr data-field-id="${index}">
            <td>
              <input type="text" class="field-type-text field-type-link field-type-email field-type-checkbox field-type-radio field-type-select" name="oum_custom_fields[${index}][label]" placeholder="Enter label" value="" />
            </td>
            <td>
              <input class="oum-switch field-type-text field-type-link field-type-email field-type-checkbox field-type-radio field-type-select" id="oum_custom_fields_${index}_required" type="checkbox" name="oum_custom_fields[${index}][required]"><label class="field-type-text field-type-link field-type-email field-type-checkbox field-type-radio field-type-select" for="oum_custom_fields_${index}_required"></label>
            </td>
            <td>
              <input class="oum-switch field-type-text field-type-link field-type-email field-type-checkbox field-type-radio field-type-select" id="oum_custom_fields_${index}_private" type="checkbox" name="oum_custom_fields[${index}][private]"><label class="field-type-text field-type-link field-type-email field-type-checkbox field-type-radio field-type-select" for="oum_custom_fields_${index}_private"></label>
            </td>
            <td>
              <input class="small-text field-type-text field-type-link field-type-email" type="number" min="0" name="oum_custom_fields[${index}][maxlength]" />
            </td>
            <td>
              <select class="oum-custom-field-fieldtype" name="oum_custom_fields[${index}][fieldtype]">                         
                  <option value="text">Text</option>
        `;

        

        fieldHTML += `
              </select>
            </td>
            <td>
              <input type="text" class="regular-text field-type-checkbox field-type-radio field-type-select" name="oum_custom_fields[${index}][options]" placeholder="Red|Blue|Green" value="" style="display: none;" />
            <label class="field-type-select oum-custom-field-allow-empty" style="display: none;"><input class="field-type-select" type="checkbox" name="oum_custom_fields[${index}][emptyoption]" />add empty option</label>
            <label class="field-type-select oum-custom-field-allow-multiple" style="display: none;"><input class="field-type-select" type="checkbox" name="oum_custom_fields[${index}][multiple]" />allow multiple</label>
              <label class="field-type-link oum-custom-field-use-label-as-text" style="display: none;"><input class="field-type-link" type="checkbox" name="oum_custom_fields[${index}][uselabelastextoption]" />use label as text</label>
              <label class="field-type-opening-hours oum-custom-field-use-12hour" style="display: none;"><input class="field-type-opening-hours" type="checkbox" name="oum_custom_fields[${index}][use12hour]" />use 12-hour format</label>
              <textarea class="regular-text field-type-html" name="oum_custom_fields[${index}][html]" placeholder="Enter HTML here" style="display: none;"></textarea>
            </td>
            <td>
              <input type="text" class="field-type-text field-type-link field-type-email field-type-checkbox field-type-radio field-type-select" name="oum_custom_fields[${index}][description]" placeholder="Enter description (optional)" value="" />
            </td>
            <td class="actions">
              <a class="up" href="#"><span class="dashicons dashicons-arrow-up"></span></a>
              <a class="down" href="#"><span class="dashicons dashicons-arrow-down"></span></a>
              <a class="remove_button" href="#"><span class="dashicons dashicons-trash"></span></a>
            </td>
          </tr>
        `;
        jQuery(wrapper).find('tbody').append(fieldHTML); //Add field html
    }
  });

  jQuery(wrapper).on('change', '.oum-custom-field-fieldtype', function(e) {
    updateCustomFieldRow(this);
  });

  jQuery('.oum-custom-field-fieldtype').each(function() {
    updateCustomFieldRow(this);
  });

  function updateCustomFieldRow(el) {
    jQuery(el).closest('tr').find('[class*="field-type-"]').hide();

    if(jQuery(el).val() == 'text') {
      jQuery(el).closest('tr').find('.field-type-text').show();
      return;
    }

    if(jQuery(el).val() == 'link') {
      jQuery(el).closest('tr').find('.field-type-link').show();
      return;
    }

    if(jQuery(el).val() == 'email') {
      jQuery(el).closest('tr').find('.field-type-email').show();
      return;
    }

    if(jQuery(el).val() == 'checkbox') {
      jQuery(el).closest('tr').find('.field-type-checkbox').show();
      return;
    }

    if(jQuery(el).val() == 'radio') {
      jQuery(el).closest('tr').find('.field-type-radio').show();
      return;
    }

    if(jQuery(el).val() == 'select') {
      jQuery(el).closest('tr').find('.field-type-select').show();
      return;
    }

    if(jQuery(el).val() == 'html') {
      jQuery(el).closest('tr').find('.field-type-html').show();
      return;
    }

    if(jQuery(el).val() == 'opening_hours') {
      jQuery(el).closest('tr').find('.field-type-text').show();
      jQuery(el).closest('tr').find('.field-type-opening-hours').show();
      return;
    }
  }

  //up button is clicked
  jQuery(wrapper).on('click', '.up', function(e) {
    e.preventDefault();
    let item = jQuery(this).closest('tr');
    item.insertBefore(item.prev());
  });

  //down button is clicked
  jQuery(wrapper).on('click', '.down', function(e) {
    e.preventDefault();
    let item = jQuery(this).closest('tr');
    item.insertAfter(item.next());
  });
  
  //remove button is clicked
  jQuery(wrapper).on('click', '.remove_button', function(e){
      e.preventDefault();
      jQuery(this).closest('tr').remove(); //Remove field html
      x--; //Decrement field counter
  });


  //Setting: Action after submit
  actionAfterSubmit(jQuery('#oum_action_after_submit').val());

  jQuery('#oum_action_after_submit').on('change', function(e){
    actionAfterSubmit(this.value);
  });

  function actionAfterSubmit(val) {
    if(val == 'text') {
      jQuery('#oum_action_after_submit_text').show();
      jQuery('#oum_action_after_submit_redirect').hide();
    }else if(val == 'redirect') {
      jQuery('#oum_action_after_submit_text').hide();
      jQuery('#oum_action_after_submit_redirect').show();
    }else{
      jQuery('#oum_action_after_submit_text').hide();
      jQuery('#oum_action_after_submit_redirect').hide();
    }
  }

  //Setting: Redirect to registration
  if(jQuery('#oum_enable_user_restriction').length > 0) {
    
    redirectToRegistration(jQuery('#oum_enable_user_restriction').is(':checked'));

    jQuery('#oum_enable_user_restriction').on('click', function(e){
      redirectToRegistration(this.checked);
    });

    function redirectToRegistration(val) {
      if(val) {
        jQuery('#redirect_to_registration').show();
      }else{
        jQuery('#redirect_to_registration').hide();
      }
    }
  }

  //Setting: Enable Filterable Marker Categories
  if(jQuery('#oum_enable_marker_types').length > 0) {
    
    toggleMarkerCategoriesSettings(jQuery('#oum_enable_marker_types').is(':checked'));

    jQuery('#oum_enable_marker_types').on('click', function(e){
      toggleMarkerCategoriesSettings(this.checked);
    });

    function toggleMarkerCategoriesSettings(val) {
      if(val) {
        // show
        jQuery('.wrap-marker-categories-settings').show();
      }else{
        // hide
        jQuery('.wrap-marker-categories-settings').hide();
      }
    }
  }

  //Setting: Enable Advanced Filter Interface
  if(jQuery('#oum_enable_advanced_filter').length > 0) {
    
    toggleAdvancedFilterSettings(jQuery('#oum_enable_advanced_filter').is(':checked'));

    jQuery('#oum_enable_advanced_filter').on('click', function(e){
      toggleAdvancedFilterSettings(this.checked);
    });

    function toggleAdvancedFilterSettings(val) {
      if(val) {
        // show
        jQuery('.wrap-advanced-filter-settings').show();
      }else{
        // hide
        jQuery('.wrap-advanced-filter-settings').hide();
      }
    }
  }

  //Setting: Geoseach Provider
  if(jQuery('#oum_geosearch_provider').length > 0) {
    
    toggleApiKeySettings(jQuery('#oum_geosearch_provider').val());

    jQuery('#oum_geosearch_provider').on('change', function(e){
      toggleApiKeySettings(e.target.value);
    });

    function toggleApiKeySettings(val) {
      jQuery('.wrap-geosearch-provider-settings > div').hide();

      if(val == 'geoapify') {
        // show
        jQuery('.geosearch-provider-geoapify').show();
      }
      if(val == 'here') {
        // show
        jQuery('.geosearch-provider-here').show();
      }
      if(val == 'mapbox') {
        // show
        jQuery('.geosearch-provider-mapbox').show();
      }
    }
  }

  //Setting: Enable Searchbar
  if(jQuery('#oum_enable_searchbar').length > 0) {
    
    toggleSearchbarSettings(jQuery('#oum_enable_searchbar').is(':checked'));

    jQuery('#oum_enable_searchbar').on('click', function(e){
      toggleSearchbarSettings(this.checked);
    });

    function toggleSearchbarSettings(val) {
      if(val) {
        // show
        jQuery('.wrap-searchbar-settings').show();
      }else{
        // hide
        jQuery('.wrap-searchbar-settings').hide();
      }
    }
  }

  // Setting: Toggle PRO Feature List
  if (jQuery('#toggle-pro-feature-list').length > 0) {
    const featuresList = jQuery('#oum-pro-features-list');
    const toggleLink = jQuery('#toggle-pro-feature-list');
    const hiddenItems = jQuery('#oum-pro-features-list .hidden-feature');

    toggleLink.on('click', function (e) {
      e.preventDefault();
      toggleProFeatureList(!hiddenItems.first().is(':visible'));
    });

    function toggleProFeatureList(show) {
      if (show) {
        featuresList.addClass('open');
        hiddenItems.slideDown(200);
        toggleLink.html('↑ Hide PRO features');
      } else {
        featuresList.removeClass('open');
        hiddenItems.slideUp(200);
        toggleLink.html('↓ Show all PRO features');
      }
    }
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

  /**
   * Advanced Filter Interface Module - Handles backend settings for the Advanced Filter Interface
   */
  const OUMAdvancedFilterSettings = (function () {
    // Private variables
    let isInitialized = false;
    let addSectionBtn = null;
    let sectionsContainer = null;

    // Private functions
    function init() {
      if (isInitialized) return;

      addSectionBtn = document.getElementById('oum-add-filter-section');
      sectionsContainer = document.getElementById('oum-advanced-filter-sections');
      
      if (!addSectionBtn || !sectionsContainer) {
        return;
      }

      setupEventListeners();
      initializeExistingSections();
      isInitialized = true;
    }

    function setupEventListeners() {
      // Add new section
      addSectionBtn.addEventListener('click', addFilterSection);

      // Handle section type changes
      sectionsContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('oum-section-type')) {
          handleSectionTypeChange(e.target);
        } else if (e.target.classList.contains('oum-custom-field-select')) {
          handleCustomFieldChange(e.target);
        }
      });

      // Handle section controls
      sectionsContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('oum-move-up')) {
          moveSection(e.target, 'up');
        } else if (e.target.classList.contains('oum-move-down')) {
          moveSection(e.target, 'down');
        } else if (e.target.classList.contains('oum-remove-section')) {
          removeSection(e.target);
        }
      });

      // Update available section types when Searchbar or Marker Categories are toggled.
      const searchbarToggle = document.getElementById('oum_enable_searchbar');
      const markerCategoriesToggle = document.getElementById('oum_enable_marker_types');
      if (searchbarToggle) {
        searchbarToggle.addEventListener('change', updateExclusiveSectionTypesAvailability);
      }
      if (markerCategoriesToggle) {
        markerCategoriesToggle.addEventListener('change', updateExclusiveSectionTypesAvailability);
      }
    }

    function hasExclusiveSectionType(type) {
      const sections = sectionsContainer.querySelectorAll('.oum-filter-section');
      return Array.from(sections).some(function(section) {
        const typeSelect = section.querySelector('.oum-section-type');
        return typeSelect && typeSelect.value === type;
      });
    }

    function getExclusiveOptionHtml(type, label, disabled) {
      if (disabled) {
        return '<option value="' + type + '" disabled>' + label + ' (enable first)</option>';
      }
      return '<option value="' + type + '">' + label + '</option>';
    }

    // Searchbar and Marker Categories can only be added when their feature toggle is on.
    function isSearchbarEnabled() {
      const checkbox = document.getElementById('oum_enable_searchbar');
      return !!(checkbox && checkbox.checked);
    }

    function isMarkerCategoriesEnabled() {
      const checkbox = document.getElementById('oum_enable_marker_types');
      return !!(checkbox && checkbox.checked);
    }

    function setNoticeVisible(element, visible) {
      if (element) {
        element.style.display = visible ? '' : 'none';
      }
    }

    function updateFeatureRequiredNotices() {
      const searchbarEnabled = isSearchbarEnabled();
      const markerCategoriesEnabled = isMarkerCategoriesEnabled();

      document.querySelectorAll('.oum-af-searchbar-section-notice').forEach(function(el) {
        setNoticeVisible(el, !searchbarEnabled);
      });
      document.querySelectorAll('.oum-af-marker-categories-section-notice').forEach(function(el) {
        setNoticeVisible(el, !markerCategoriesEnabled);
      });
    }

    // Keep exclusive section types available in only one section at a time.
    // When the matching feature is off, show a disabled "enable first" option instead of hiding it.
    // An existing section of that type keeps its option enabled so the value still saves.
    function updateExclusiveSectionTypeAvailability(type, label, featureEnabled) {
      const taken = hasExclusiveSectionType(type);

      sectionsContainer.querySelectorAll('.oum-section-type').forEach(function(select) {
        const isThisType = select.value === type;
        let option = select.querySelector('option[value="' + type + '"]');

        if (isThisType) {
          if (!option) {
            select.insertAdjacentHTML('beforeend', getExclusiveOptionHtml(type, label, false));
            option = select.querySelector('option[value="' + type + '"]');
          }
          if (option) {
            option.disabled = false;
            option.textContent = label;
            option.selected = true;
          }
          return;
        }

        if (taken) {
          if (option) {
            option.remove();
          }
          return;
        }

        if (!option) {
          select.insertAdjacentHTML('beforeend', getExclusiveOptionHtml(type, label, !featureEnabled));
          option = select.querySelector('option[value="' + type + '"]');
        }

        if (option) {
          option.disabled = !featureEnabled;
          option.textContent = featureEnabled ? label : label + ' (enable first)';
        }
      });
    }

    function updateExclusiveSectionTypesAvailability() {
      updateExclusiveSectionTypeAvailability('marker_categories', 'Marker Categories', isMarkerCategoriesEnabled());
      updateExclusiveSectionTypeAvailability('searchbar', 'Searchbar', isSearchbarEnabled());
      updateFeatureRequiredNotices();
    }

    function addFilterSection() {
      const existingSections = sectionsContainer.querySelectorAll('.oum-filter-section');
      const newIndex = existingSections.length;
      
      // Get available custom fields from the page
      const availableCustomFields = getAvailableCustomFields();
      const markerCategoriesOption = hasExclusiveSectionType('marker_categories') ? '' : getExclusiveOptionHtml('marker_categories', 'Marker Categories', !isMarkerCategoriesEnabled());
      const searchbarOption = hasExclusiveSectionType('searchbar') ? '' : getExclusiveOptionHtml('searchbar', 'Searchbar', !isSearchbarEnabled());
      
      const sectionHTML = `
        <div class="oum-filter-section" data-index="${newIndex}">
        <div class="oum-section-header">
          <h4>Filter Section #${newIndex + 1}</h4>
          <div class="oum-section-controls">
            <button type="button" class="button oum-move-up" title="Move section up">↑</button>
            <button type="button" class="button oum-move-down" title="Move section down">↓</button>
            <button type="button" class="button oum-remove-section" title="Remove this section">×</button>
          </div>
        </div>
          <div class="oum-section-content">
            <table class="form-table">
              <tr>
                <th scope="row">Section Type</th>
                <td>
                  <select name="oum_advanced_filter_sections[${newIndex}][type]" class="oum-section-type">
                    <option value="custom_field">Custom Field Filter</option>
                    <option value="html">Custom HTML Content</option>
                    ${markerCategoriesOption}
                    ${searchbarOption}
                  </select>
                  <div class="description">Choose whether this section filters by a Custom Field, Marker Categories, Searchbar or displays custom HTML.</div>
                </td>
              </tr>
              <tr class="oum-custom-field-options active">
                <th scope="row">Filter Field</th>
                <td>
                  <select name="oum_advanced_filter_sections[${newIndex}][custom_field_id]" class="oum-custom-field-select">
                    ${availableCustomFields}
                  </select>
                  <div class="description">Select which custom field to use for filtering locations.</div>
                </td>
              </tr>
              <tr class="oum-checkbox-relation-options oum-custom-field-options" style="display: none;">
                <th scope="row">Checkbox Relation</th>
                <td>
                  <select name="oum_advanced_filter_sections[${newIndex}][checkbox_relation]">
                    <option value="OR">OR - Show locations matching ANY selected value</option>
                    <option value="AND">AND - Show locations matching ALL selected values</option>
                  </select>
                  <div class="description">Choose how multiple checkbox selections are combined. OR shows locations matching any selected value, AND shows locations matching all selected values.</div>
                </td>
              </tr>
              <tr class="oum-html-options">
                <th scope="row">HTML Content</th>
                <td>
                  <textarea name="oum_advanced_filter_sections[${newIndex}][html_content]" rows="5" cols="50" placeholder="<h3>Custom Section</h3><p>Enter your HTML content here...</p>"></textarea>
                  <div class="description">Enter custom HTML content that will be displayed in the filter sidebar. You can use headings, text, links, or any HTML elements.</div>
                </td>
              </tr>
            </table>
          </div>
        </div>
      `;
      
      sectionsContainer.insertAdjacentHTML('beforeend', sectionHTML);
      updateSectionNumbers();
      updateExclusiveSectionTypesAvailability();
    }

    function getAvailableCustomFields() {
      // Look for custom fields from the Form Settings section
      const customFieldRows = document.querySelectorAll('.oum_custom_fields_wrapper tbody tr');
      if (customFieldRows.length === 0) {
        return '<option value="">No custom fields available</option>';
      }

      let options = '<option value="">Select Custom Field</option>';
      
      customFieldRows.forEach((row, index) => {
        // Prefer the data attribute, but keep a fallback for backwards compatibility
        const dataFieldId = row.getAttribute('data-field-id');
        const labelInput = row.querySelector('input[name*="[label]"]');
        const fieldtypeSelect = row.querySelector('select[name*="[fieldtype]"]');
        const fieldId = dataFieldId || extractFieldIdFromName(labelInput);
        
        if (labelInput && fieldtypeSelect && fieldId) {
          const label = labelInput.value.trim();
          const fieldtype = fieldtypeSelect.value;
          
          if (label && fieldtype !== 'html') {
            options += `<option value="${fieldId}" data-fieldtype="${fieldtype}">${label} (${fieldtype})</option>`;
          }
        }
      });

      return options;
      
      function extractFieldIdFromName(input) {
        if (!input) {
          return null;
        }
        const name = input.getAttribute('name');
        if (!name) {
          return null;
        }
        const match = name.match(/oum_custom_fields\[(.+?)\]/);
        return match && match[1] ? match[1] : null;
      }
    }

    function handleSectionTypeChange(selectElement) {
      const section = selectElement.closest('.oum-filter-section');
      const customFieldOptions = section.querySelector('.oum-custom-field-options');
      const htmlOptions = section.querySelector('.oum-html-options');
      const markerCategoriesOptions = section.querySelector('.oum-marker-categories-options');
      const searchbarOptions = section.querySelector('.oum-searchbar-options');
      
      if (selectElement.value === 'custom_field') {
        if (customFieldOptions) {
          customFieldOptions.classList.add('active');
          customFieldOptions.style.display = '';
        }
        if (htmlOptions) {
          htmlOptions.classList.remove('active');
          htmlOptions.style.display = 'none';
        }
        if (markerCategoriesOptions) {
          markerCategoriesOptions.classList.remove('active');
          markerCategoriesOptions.style.display = 'none';
        }
        if (searchbarOptions) {
          searchbarOptions.classList.remove('active');
          searchbarOptions.style.display = 'none';
        }
        // Check if checkbox field is selected and show relation option
        const customFieldSelect = section.querySelector('.oum-custom-field-select');
        if (customFieldSelect) {
          handleCustomFieldChange(customFieldSelect);
        }
      } else if (selectElement.value === 'html') {
        if (customFieldOptions) {
          customFieldOptions.classList.remove('active');
          customFieldOptions.style.display = 'none';
        }
        if (htmlOptions) {
          htmlOptions.classList.add('active');
          htmlOptions.style.display = '';
        }
        if (markerCategoriesOptions) {
          markerCategoriesOptions.classList.remove('active');
          markerCategoriesOptions.style.display = 'none';
        }
        if (searchbarOptions) {
          searchbarOptions.classList.remove('active');
          searchbarOptions.style.display = 'none';
        }
        // Hide checkbox relation when switching to HTML
        const checkboxRelationRow = section.querySelector('.oum-checkbox-relation-options');
        if (checkboxRelationRow) {
          checkboxRelationRow.classList.remove('active');
          checkboxRelationRow.style.display = 'none';
        }
      } else if (selectElement.value === 'marker_categories') {
        if (customFieldOptions) {
          customFieldOptions.classList.remove('active');
          customFieldOptions.style.display = 'none';
        }
        if (htmlOptions) {
          htmlOptions.classList.remove('active');
          htmlOptions.style.display = 'none';
        }
        if (markerCategoriesOptions) {
          markerCategoriesOptions.classList.add('active');
          markerCategoriesOptions.style.display = '';
        }
        if (searchbarOptions) {
          searchbarOptions.classList.remove('active');
          searchbarOptions.style.display = 'none';
        }
        const checkboxRelationRow = section.querySelector('.oum-checkbox-relation-options');
        if (checkboxRelationRow) {
          checkboxRelationRow.classList.remove('active');
          checkboxRelationRow.style.display = 'none';
        }
      } else if (selectElement.value === 'searchbar') {
        if (customFieldOptions) {
          customFieldOptions.classList.remove('active');
          customFieldOptions.style.display = 'none';
        }
        if (htmlOptions) {
          htmlOptions.classList.remove('active');
          htmlOptions.style.display = 'none';
        }
        if (markerCategoriesOptions) {
          markerCategoriesOptions.classList.remove('active');
          markerCategoriesOptions.style.display = 'none';
        }
        if (searchbarOptions) {
          searchbarOptions.classList.add('active');
          searchbarOptions.style.display = '';
        }
        const checkboxRelationRow = section.querySelector('.oum-checkbox-relation-options');
        if (checkboxRelationRow) {
          checkboxRelationRow.classList.remove('active');
          checkboxRelationRow.style.display = 'none';
        }
      }

      updateExclusiveSectionTypesAvailability();
    }

    function handleCustomFieldChange(selectElement) {
      const section = selectElement.closest('.oum-filter-section');
      const checkboxRelationRow = section.querySelector('.oum-checkbox-relation-options');
      
      if (!checkboxRelationRow) {
        return;
      }
      
      const selectedOption = selectElement.options[selectElement.selectedIndex];
      const fieldType = selectedOption ? selectedOption.getAttribute('data-fieldtype') : null;
      
      // Show checkbox relation option only if checkbox field is selected
      if (fieldType === 'checkbox') {
        checkboxRelationRow.classList.add('active');
        checkboxRelationRow.style.display = '';
      } else {
        checkboxRelationRow.classList.remove('active');
        checkboxRelationRow.style.display = 'none';
      }
    }

    function moveSection(button, direction) {
      const section = button.closest('.oum-filter-section');
      
      if (direction === 'up') {
        const prevSection = section.previousElementSibling;
        if (prevSection) {
          sectionsContainer.insertBefore(section, prevSection);
        }
      } else if (direction === 'down') {
        const nextSection = section.nextElementSibling;
        if (nextSection) {
          sectionsContainer.insertBefore(nextSection, section);
        }
      }
      
      updateSectionNumbers();
    }

    function removeSection(button) {
      if (confirm('Are you sure you want to remove this section?')) {
        const section = button.closest('.oum-filter-section');
        section.remove();
        updateSectionNumbers();
        updateExclusiveSectionTypesAvailability();
      }
    }

    function updateSectionNumbers() {
      const sections = document.querySelectorAll('.oum-filter-section');
      sections.forEach((section, index) => {
      const header = section.querySelector('.oum-section-header h4');
      if (header) {
        header.textContent = `Filter Section #${index + 1}`;
      }
        
        // Update data-index and form field names
        section.setAttribute('data-index', index);
        
        // Update all form field names
        const formFields = section.querySelectorAll('input, select, textarea');
        formFields.forEach(field => {
          const name = field.getAttribute('name');
          if (name) {
            const newName = name.replace(/\[\d+\]/, `[${index}]`);
            field.setAttribute('name', newName);
          }
        });
      });
    }

    function initializeExistingSections() {
      const sections = document.querySelectorAll('.oum-filter-section');
      sections.forEach(section => {
        const typeSelect = section.querySelector('.oum-section-type');
        if (typeSelect) {
          // Initialize the section based on its current type
          handleSectionTypeChange(typeSelect);
        }
        // Also check custom field selection for existing sections
        const customFieldSelect = section.querySelector('.oum-custom-field-select');
        if (customFieldSelect) {
          handleCustomFieldChange(customFieldSelect);
        }
      });
      updateExclusiveSectionTypesAvailability();
    }

    // Public interface
    return {
      init: init
    };
  })();

  // Initialize Advanced Filter Interface Settings
  OUMAdvancedFilterSettings.init();

}, false);