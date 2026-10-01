/**
 * Opening Hours Module - Handles parsing, validation, formatting, and UI interactions for opening hours
 */
const OUMOpeningHours = (function() {
  // Private variables
  let observer = null;

  /**
   * English fallbacks used when locale data has not been localized yet
   */
  const fallbackDayAbbrs = {
    mo: 'Mo',
    tu: 'Tu',
    we: 'We',
    th: 'Th',
    fr: 'Fr',
    sa: 'Sa',
    su: 'Su'
  };
  const fallbackDayAliases = {
    mo: 'mo',
    tu: 'tu',
    we: 'we',
    th: 'th',
    fr: 'fr',
    sa: 'sa',
    su: 'su'
  };

  /**
   * Locale weekday aliases and abbreviations from wp_localize_script
   */
  function getDayConfig() {
    if (typeof oum_opening_hours !== 'undefined' && oum_opening_hours) {
      return {
        aliases: oum_opening_hours.day_aliases || fallbackDayAliases,
        abbrs: oum_opening_hours.day_abbrs || fallbackDayAbbrs
      };
    }

    return {
      aliases: fallbackDayAliases,
      abbrs: fallbackDayAbbrs
    };
  }

  /**
   * Normalize a weekday token for alias lookup (trim, strip dots, lowercase, fold accents)
   * @param {string} alias
   * @returns {string}
   */
  function normalizeDayAlias(alias) {
    if (!alias || typeof alias !== 'string') {
      return '';
    }

    let value = alias.trim().replace(/\.+$/, '').toLowerCase();

    try {
      value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    } catch (e) {
      // ignore if normalize() is unavailable
    }

    return value;
  }

  /**
   * Resolve a typed weekday token to an internal day key (mo, tu, ...)
   * @param {string} token
   * @returns {string|null}
   */
  function resolveDayKey(token) {
    const normalized = normalizeDayAlias(token);
    const aliases = getDayConfig().aliases;

    if (normalized && aliases[normalized]) {
      return aliases[normalized];
    }

    return null;
  }

  /**
   * Converts 12-hour format time to 24-hour format
   * @param {string} time12 - Time in 12-hour format (e.g., "9:00 AM" or "11:30 PM")
   * @returns {string|null} - Time in 24-hour format (HH:MM) or null if invalid
   */
  function convert12To24(time12) {
    if (!time12 || typeof time12 !== 'string') {
      return null;
    }
    
    // Pattern: H:MM or HH:MM followed by AM/PM (case insensitive)
    const pattern = /^(\d{1,2}):(\d{2})\s*(AM|PM)$/i;
    const match = time12.trim().match(pattern);
    
    if (!match) {
      return null;
    }
    
    let hour = parseInt(match[1], 10);
    const minute = parseInt(match[2], 10);
    const period = match[3].toUpperCase();
    
    // Validate minute
    if (minute < 0 || minute > 59) {
      return null;
    }
    
    // Convert to 24-hour format
    if (period === 'AM') {
      if (hour === 12) {
        hour = 0; // 12:00 AM = 00:00
      }
    } else { // PM
      if (hour !== 12) {
        hour += 12; // 1:00 PM = 13:00, but 12:00 PM = 12:00
      }
    }
    
    // Validate hour
    if (hour < 0 || hour > 23) {
      return null;
    }
    
    return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
  }

  /**
   * Validates the opening hours input format
   * Format: Mo 09:00-18:00 | Di 9:00 AM-5:00 PM | Do 1:00 PM-6:00 PM
   * Accepts English weekday abbreviations plus the current WordPress locale (e.g. Do for Thursday)
   * Supports both 24-hour (HH:MM) and 12-hour (H:MM AM/PM) formats
   * @param {string} input - The input string to validate
   * @returns {boolean} - True if valid, false otherwise
   */
  function validateFormat(input) {
    if (!input || typeof input !== 'string') {
      return false;
    }

    const trimmed = input.trim();
    if (trimmed === '') {
      return false;
    }

    // Split by pipe and validate each block
    const blocks = trimmed.split('|');
    // Day token is language-agnostic; aliases are resolved via resolveDayKey()
    const pattern24 = /^(\S+)\s+(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/;
    const pattern12 = /^(\S+)\s+(\d{1,2}):(\d{2})\s*(AM|PM)-(\d{1,2}):(\d{2})\s*(AM|PM)$/i;

    for (let block of blocks) {
      block = block.trim();
      if (block === '') {
        continue;
      }

      const match12 = block.match(pattern12);
      if (match12) {
        if (!resolveDayKey(match12[1])) {
          return false;
        }

        // Validate 12-hour format times
        const startTime12 = match12[2] + ':' + match12[3] + ' ' + match12[4];
        const endTime12 = match12[5] + ':' + match12[6] + ' ' + match12[7];
        const startTime24 = convert12To24(startTime12);
        const endTime24 = convert12To24(endTime12);
        
        if (!startTime24 || !endTime24) {
          return false;
        }
      } else {
        // Validate 24-hour format times
        const match = block.match(pattern24);
        if (!match || !resolveDayKey(match[1])) {
          return false;
        }
        
        const startHour = parseInt(match[2], 10);
        const startMin = parseInt(match[3], 10);
        const endHour = parseInt(match[4], 10);
        const endMin = parseInt(match[5], 10);
        
        // Check valid time ranges
        if (startHour < 0 || startHour > 23 || startMin < 0 || startMin > 59 ||
            endHour < 0 || endHour > 23 || endMin < 0 || endMin > 59) {
          return false;
        }
      }
    }
    
    return true;
  }

  /**
   * Converts 24-hour format time to 12-hour format
   * @param {string} time24 - Time in 24-hour format (HH:MM)
   * @returns {string} - Time in 12-hour format (H:MM AM/PM)
   */
  function convert24To12(time24) {
    if (!time24 || !time24.match(/^\d{2}:\d{2}$/)) {
      return time24; // Return as-is if invalid format
    }
    
    const parts = time24.split(':');
    let hour = parseInt(parts[0], 10);
    const minute = parts[1];
    let period = 'AM';
    
    if (hour === 0) {
      hour = 12; // 00:00 = 12:00 AM
    } else if (hour === 12) {
      period = 'PM'; // 12:00 = 12:00 PM
    } else if (hour > 12) {
      hour -= 12;
      period = 'PM';
    }
    
    return hour + ':' + minute + ' ' + period;
  }

  /**
   * Converts opening hours JSON back to input format
   * @param {Object} data - The opening hours JSON object
   * @param {boolean} use12hour - Whether to use 12-hour format
   * @returns {string} - Input format string
   */
  function formatForInput(data, use12hour = false) {
    if (!data || !data.week) {
      return '';
    }

    const dayAbbrs = getDayConfig().abbrs;

    const blocks = [];
    const days = ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su'];

    for (const day of days) {
      const dayAbbr = dayAbbrs[day];
      if (!dayAbbr) {
        continue;
      }

      const timeBlocks = data.week[day] || [];
      
      for (const block of timeBlocks) {
        if (use12hour) {
          const startTime = convert24To12(block.start);
          const endTime = convert24To12(block.end);
          blocks.push(`${dayAbbr} ${startTime}-${endTime}`);
        } else {
          blocks.push(`${dayAbbr} ${block.start}-${block.end}`);
        }
      }
    }

    return blocks.join(' | ');
  }

  /**
   * Setup toggle functionality for opening hours headers
   * @param {HTMLElement} container - Container element to search for headers
   */
  function setupToggle(container) {
    if (!container) return;
    
    const headers = container.querySelectorAll('.oum-opening-hours-header');
    headers.forEach(function(header) {
      // Skip if already has event listener (check for data attribute)
      if (header.dataset.oumToggleSetup === 'true') {
        return;
      }
      
      // Mark as setup
      header.dataset.oumToggleSetup = 'true';
      
      // Add click handler
      header.addEventListener('click', function(e) {
        e.preventDefault();
        toggleOpeningHours(header);
      });
      
      // Add keyboard support
      header.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          toggleOpeningHours(header);
        }
      });
    });
  }

  /**
   * Toggle the visibility of opening hours wrapper
   * @param {HTMLElement} header - The header element containing the toggle
   */
  function toggleOpeningHours(header) {
    const wrapper = header.nextElementSibling;
    if (wrapper && wrapper.classList.contains('oum-opening-hours-wrapper')) {
      const isExpanded = wrapper.classList.contains('oum-opening-hours-expanded');
      if (isExpanded) {
        wrapper.classList.remove('oum-opening-hours-expanded');
        wrapper.style.display = 'none';
        header.classList.remove('oum-opening-hours-expanded');
      } else {
        wrapper.classList.add('oum-opening-hours-expanded');
        wrapper.style.display = 'block';
        header.classList.add('oum-opening-hours-expanded');
      }
    }
  }

  /**
   * Initialize MutationObserver for dynamically added content
   */
  function initializeObserver() {
    if (observer) {
      return; // Already initialized
    }

    observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.addedNodes.length) {
          mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) { // Element node
              // Check if this node or its children contain opening hours header
              if (node.querySelector && node.querySelector('.oum-opening-hours-header')) {
                setupToggle(node);
              }
              // Also check if the node itself is an opening hours header
              if (node.classList && node.classList.contains('oum-opening-hours-header')) {
                setupToggle(node.parentElement);
              }
            }
          });
        }
      });
    });
    
    // Start observing
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  /**
   * Initialize opening hours UI for existing content
   */
  function init() {
    setupToggle(document.body);
    initializeObserver();
  }

  // Public interface
  return {
    validateFormat: validateFormat,
    formatForInput: formatForInput,
    // UI methods
    init: init,
    setupToggle: setupToggle
  };
})();

