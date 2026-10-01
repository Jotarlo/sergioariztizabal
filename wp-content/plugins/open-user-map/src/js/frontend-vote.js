/**
 * Vote Button Module - Handles vote/unvote functionality and star ratings for location markers
 * 
 * Cookie Types:
 * - Persistent cookies: Expire after 1 year (default)
 * - Session cookies: Expire when browser closes (privacy-friendly)
 * - No cookies: Users can vote multiple times (stored in global variables, cleared on page refresh)
 * 
 * Cookie naming: 
 * - Upvote: oum_voted_{postId} = '1'
 * - Star Rating: oum_star_rating_{postId} = rating (1-5)
 */
const OUMVoteHandler = (function () {
  // Global variables for vote states when no cookies are used
  let sessionVotes = new Set();
  // Global variables for star ratings when no cookies are used
  let sessionStarRatings = new Map(); // postId -> rating (1-5)
  
  // Get cookie type from PHP (will be set by wp_localize_script)
  const cookieType = window.oum_vote_cookie_type?.type || 'persistent';
  
  function initializeVoteButtons() {
    // Add event listeners to existing vote buttons
    document.addEventListener('click', function(e) {
      if (e.target.closest('.oum_vote_button')) {
        e.preventDefault();
        handleVoteClick(e.target.closest('.oum_vote_button'));
      }
      // Handle star rating clicks
      if (e.target.closest('.oum_star_rating')) {
        e.preventDefault();
        const starElement = e.target.closest('.oum_star');
        if (starElement) {
          handleStarClick(starElement);
        }
      }
    });

    // Add hover event listeners for star ratings
    document.addEventListener('mouseover', function(e) {
      const starElement = e.target.closest('.oum_star');
      if (starElement) {
        handleStarHover(starElement);
      }
    }, true);

    document.addEventListener('mouseout', function(e) {
      const starRating = e.target.closest('.oum_star_rating');
      if (starRating) {
        const relatedTarget = e.relatedTarget;
        // Only remove hover if leaving the star rating container entirely
        if (!relatedTarget || !starRating.contains(relatedTarget)) {
          const stars = starRating.querySelectorAll('.oum_star');
          stars.forEach(star => {
            star.classList.remove('hover');
          });
        }
      }
    }, true);

    // Update vote button states based on cookies/session
    updateVoteButtonStates();
    
    // Initialize star ratings
    initializeStarRatings();
    
    // Initialize vote counts from existing data on page load (no AJAX needed)
    initializeVoteCountsFromData();
    
    // Use MutationObserver to watch for new vote buttons and star ratings being added
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.type === 'childList') {
          const voteButtons = mutation.target.querySelectorAll('.oum_vote_button');
          if (voteButtons.length > 0) {
            updateVoteButtonStates();
            // Refresh vote counts for new popups (only for dynamically added content)
            refreshVoteCounts(voteButtons);
          }
          // Initialize star ratings for new popups
          const starRatings = mutation.target.querySelectorAll('.oum_star_rating');
          if (starRatings.length > 0) {
            initializeStarRatings();
          }
        }
      });
    });

    // Start observing the document body for changes
    observer.observe(document.body, {
      childList: true,
      subtree: true
    });
  }

  /**
   * Initialize vote counts from existing location data (no AJAX needed)
   * This uses the vote data already available in oum_all_locations
   */
  function initializeVoteCountsFromData() {
    const allVoteButtons = document.querySelectorAll('.oum_vote_button');
    
    allVoteButtons.forEach(button => {
      const postId = button.getAttribute('data-post-id');
      if (postId) {
        // Try to get vote count from existing location data
        let voteCount = 0;
        
        // Check if we have access to oum_all_locations data
        if (typeof window.oum_all_locations !== 'undefined' && Array.isArray(window.oum_all_locations)) {
          const location = window.oum_all_locations.find(loc => loc.post_id === postId);
          if (location && typeof location.votes !== 'undefined') {
            voteCount = parseInt(location.votes) || 0;
          }
        }
        
        // If we couldn't find the data, use the data-votes attribute as fallback
        if (voteCount === 0) {
          voteCount = parseInt(button.getAttribute('data-votes') || '0');
        }
        
        // Update the button with the vote count
        updateVoteButtonDisplay(button, voteCount);
      }
    });
  }

  /**
   * Update vote button display without making AJAX calls
   * @param {HTMLElement} button - The vote button element
   * @param {number} voteCount - The vote count to display
   */
  function updateVoteButtonDisplay(button, voteCount) {
    // Update data attribute
    button.setAttribute('data-votes', voteCount);
    
    // Update counter display
    const countElement = button.querySelector('.oum_vote_count');
    if (voteCount > 0) {
      if (countElement) {
        countElement.textContent = voteCount;
        countElement.style.display = 'inline';
      } else {
        // Create counter element if it doesn't exist
        const newCountElement = document.createElement('span');
        newCountElement.className = 'oum_vote_count';
        newCountElement.textContent = voteCount;
        button.appendChild(newCountElement);
      }
    } else {
      // Hide counter if count is 0
      if (countElement) {
        countElement.style.display = 'none';
      }
    }
  }

  /**
   * Update the oum_all_locations data to keep vote counts in sync
   * @param {string} postId - The location post ID
   * @param {number} voteCount - The new vote count
   */
  function updateLocationData(postId, voteCount) {
    if (typeof window.oum_all_locations !== 'undefined' && Array.isArray(window.oum_all_locations)) {
      const locationIndex = window.oum_all_locations.findIndex(loc => loc.post_id === postId);
      if (locationIndex !== -1) {
        window.oum_all_locations[locationIndex].votes = voteCount;
      }
    }
    if (typeof OUMMarkers !== 'undefined' && OUMMarkers.invalidateBubbleHtmlCacheForPost) {
      OUMMarkers.invalidateBubbleHtmlCacheForPost(postId);
    }
  }

  function handleVoteClick(button) {
    const postId = button.getAttribute('data-post-id');
    const currentVotes = parseInt(button.getAttribute('data-votes') || '0');
    const isVoted = button.classList.contains('voted');

    // Show loading state
    button.style.pointerEvents = 'none';
    const originalText = button.querySelector('.oum_vote_text').textContent;
    button.querySelector('.oum_vote_text').textContent = isVoted ? 'Unvoting...' : 'Voting...';

    // Prepare AJAX data
    const formData = new FormData();
    formData.append('action', 'oum_toggle_vote');
    formData.append('post_id', postId);
    formData.append('nonce', oum_vote_nonce.nonce);
    
    // For no-cookie mode, send current vote state to help server determine action
    if (cookieType === 'none') {
      formData.append('current_vote_state', isVoted ? 'voted' : 'not_voted');
    }

    // Send AJAX request
    fetch(oum_ajax.ajaxurl, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update session variables for no-cookie mode
        if (cookieType === 'none') {
          if (data.data.voted) {
            sessionVotes.add(postId);
          } else {
            sessionVotes.delete(postId);
          }
        }
        
        // Update button state
        updateVoteButton(button, data.data.voted, data.data.votes);
        
        // Update the oum_all_locations data to keep it in sync
        updateLocationData(postId, data.data.votes);
        
        // No need to refresh all vote buttons - the current button is already updated
        // and other buttons will be updated when their popups are opened or when
        // the page is refreshed
      } else {
        // Restore original state on error
        button.querySelector('.oum_vote_text').textContent = originalText;
      }
    })
    .catch(error => {
      console.error('Vote request failed:', error);
      button.querySelector('.oum_vote_text').textContent = originalText;
    })
    .finally(() => {
      // Re-enable button
      button.style.pointerEvents = 'auto';
    });
  }

  function updateVoteButton(button, isVoted, voteCount) {
    const countElement = button.querySelector('.oum_vote_count');
    const textElement = button.querySelector('.oum_vote_text');
    const voteLabel = button.getAttribute('data-label') || 'Vote';

    // Handle empty values by using fallbacks
    const displayVoteLabel = voteLabel.trim() || 'Vote';

    // Always restore the correct text
    if (textElement) {
      textElement.textContent = displayVoteLabel;
    }

    // Update vote count - show/hide counter based on count
    if (voteCount > 0) {
      if (countElement) {
        countElement.textContent = voteCount;
        countElement.style.display = 'inline';
      } else {
        // Create counter element if it doesn't exist
        const newCountElement = document.createElement('span');
        newCountElement.className = 'oum_vote_count';
        newCountElement.textContent = voteCount;
        button.appendChild(newCountElement);
      }
    } else {
      // Hide counter if count is 0
      if (countElement) {
        countElement.style.display = 'none';
      }
    }

    // Update button state - always show "Vote" text
    if (isVoted) {
      button.classList.add('voted');
    } else {
      button.classList.remove('voted');
    }

    // Update data attribute
    button.setAttribute('data-votes', voteCount);
  }

  function updateVoteButtonStates() {
    const voteButtons = document.querySelectorAll('.oum_vote_button');
    
    voteButtons.forEach(button => {
      const postId = button.getAttribute('data-post-id');
      
      // Check if user has voted this location
      if (isLocationVoted(postId)) {
        button.classList.add('voted');
      }
    });
  }

  function isLocationVoted(postId) {
    // First check session variables (for no-cookie mode)
    if (sessionVotes.has(postId)) {
      return true;
    }
    
    // Then check cookies (for persistent/session modes)
    const cookieName = 'oum_voted_' + postId;
    const cookies = document.cookie.split(';');
    
    for (let i = 0; i < cookies.length; i++) {
      const cookie = cookies[i].trim();
      if (cookie.startsWith(cookieName + '=')) {
        const value = cookie.substring(cookieName.length + 1);
        return value === '1';
      }
    }
    
    return false;
  }

  function refreshVoteCounts(buttons) {
    buttons.forEach(button => {
      const postId = button.getAttribute('data-post-id');
      if (postId) {
        // Prepare AJAX data
        const formData = new FormData();
        formData.append('action', 'oum_get_vote_count');
        formData.append('post_id', postId);
        formData.append('nonce', oum_vote_nonce.nonce);

        // Send AJAX request to get updated count
        fetch(oum_ajax.ajaxurl, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Update the button with the fresh count
            const currentVotes = parseInt(button.getAttribute('data-votes') || '0');
            const newVotes = data.data.votes;
            
            // Only update if the count has changed
            if (currentVotes !== newVotes) {
              button.setAttribute('data-votes', newVotes);
              
              // Update counter display
              const countElement = button.querySelector('.oum_vote_count');
              if (newVotes > 0) {
                if (countElement) {
                  countElement.textContent = newVotes;
                  countElement.style.display = 'inline';
                } else {
                  // Create counter element if it doesn't exist
                  const newCountElement = document.createElement('span');
                  newCountElement.className = 'oum_vote_count';
                  newCountElement.textContent = newVotes;
                  button.appendChild(newCountElement);
                }
              } else {
                // Hide counter if count is 0
                if (countElement) {
                  countElement.style.display = 'none';
                }
              }
            }
          }
        })
        .catch(error => {
          console.error('Failed to refresh vote count:', error);
        });
      }
    });
  }

  /**
   * Initialize star ratings from existing data
   * This uses the star rating data already available in oum_all_locations
   */
  function initializeStarRatings() {
    const allStarRatings = document.querySelectorAll('.oum_star_rating');
    
    allStarRatings.forEach(starRating => {
      const postId = starRating.getAttribute('data-post-id');
      if (postId) {
        // Try to get star rating data from existing location data first
        let average = 0;
        let count = 0;
        
        // Check if we have access to oum_all_locations data
        if (typeof window.oum_all_locations !== 'undefined' && Array.isArray(window.oum_all_locations)) {
          const location = window.oum_all_locations.find(loc => loc.post_id === postId);
          if (location) {
            if (typeof location.star_rating_avg !== 'undefined') {
              average = parseFloat(location.star_rating_avg) || 0;
            }
            if (typeof location.star_rating_count !== 'undefined') {
              count = parseInt(location.star_rating_count) || 0;
            }
          }
        }
        
        // If we couldn't find the data in oum_all_locations, use data attributes as fallback
        if (average === 0 && count === 0) {
          average = parseFloat(starRating.getAttribute('data-average') || '0');
          count = parseInt(starRating.getAttribute('data-count') || '0');
        }
        
        // Update display with the latest data
        updateStarRatingDisplay(starRating, average, count);
      }
    });
  }

  /**
   * Get user's star rating for a location (from cookie or session)
   * @param {string} postId - The location post ID
   * @returns {number} User's rating (0 if not rated)
   */
  function getUserStarRating(postId) {
    // First check session variables (for no-cookie mode)
    if (sessionStarRatings.has(postId)) {
      return sessionStarRatings.get(postId);
    }
    
    // Then check cookies (for persistent/session modes)
    const cookieName = 'oum_star_rating_' + postId;
    const cookies = document.cookie.split(';');
    
    for (let i = 0; i < cookies.length; i++) {
      const cookie = cookies[i].trim();
      if (cookie.startsWith(cookieName + '=')) {
        const value = cookie.substring(cookieName.length + 1);
        return parseInt(value) || 0;
      }
    }
    
    return 0;
  }

  /**
   * Handle star click
   * @param {HTMLElement} starElement - The clicked star element
   */
  function handleStarClick(starElement) {
    const starRating = starElement.closest('.oum_star_rating');
    if (!starRating) return;
    
    const postId = starRating.getAttribute('data-post-id');
    const rating = parseInt(starElement.getAttribute('data-rating') || '0');
    
    if (!postId || rating < 1 || rating > 5) return;
    
    // Disable interaction during request
    starRating.style.pointerEvents = 'none';
    
    // Prepare AJAX data
    const formData = new FormData();
    formData.append('action', 'oum_toggle_vote');
    formData.append('post_id', postId);
    formData.append('star_rating', rating);
    formData.append('nonce', oum_vote_nonce.nonce);
    
    // For no-cookie mode, send current rating to help server determine action
    if (cookieType === 'none') {
      const currentRating = getUserStarRating(postId);
      formData.append('current_rating', currentRating);
    }

    // Send AJAX request
    fetch(oum_ajax.ajaxurl, {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Update session variables for no-cookie mode
        if (cookieType === 'none') {
          sessionStarRatings.set(postId, rating);
        }
        
        // Update display
        updateStarRatingDisplay(starRating, data.data.average, data.data.count);
        
        // Update location data if available
        updateStarRatingLocationData(postId, data.data.average, data.data.count);
      } else {
        // Handle error response from server
        console.error('Star rating request failed:', data.data?.message || 'Unknown error');
        // Re-enable interaction on error
        starRating.style.pointerEvents = 'auto';
      }
    })
    .catch(error => {
      console.error('Star rating request failed:', error);
      // Re-enable interaction on error
      starRating.style.pointerEvents = 'auto';
    })
    .finally(() => {
      // Re-enable interaction
      starRating.style.pointerEvents = 'auto';
    });
  }

  /**
   * Handle star hover - highlight stars 1 through the hovered star
   * @param {HTMLElement} starElement - The hovered star element
   */
  function handleStarHover(starElement) {
    const starRating = starElement.closest('.oum_star_rating');
    if (!starRating) return;
    
    const stars = starRating.querySelectorAll('.oum_star');
    const hoveredRating = parseInt(starElement.getAttribute('data-rating') || '0');
    
    // Remove hover from all stars first
    stars.forEach(star => {
      star.classList.remove('hover');
    });
    
    // Add hover to stars 1 through the hovered star
    stars.forEach((star, index) => {
      const starValue = index + 1;
      if (starValue <= hoveredRating) {
        star.classList.add('hover');
      }
    });
  }

  /**
   * Update star rating display
   * @param {HTMLElement} starRating - The star rating container
   * @param {number} average - Average rating
   * @param {number} count - Number of ratings
   */
  function updateStarRatingDisplay(starRating, average, count) {
    // Update data attributes
    starRating.setAttribute('data-average', average);
    starRating.setAttribute('data-count', count);
    
    // Round average to nearest integer for display
    const roundedAverage = Math.round(average);
    
    // Update stars visual state - always show rounded average
    const stars = starRating.querySelectorAll('.oum_star');
    stars.forEach((star, index) => {
      const starValue = index + 1;
      star.classList.remove('active', 'half');
      
      // Show rounded average rating
      if (starValue <= roundedAverage) {
        star.classList.add('active');
      }
    });
    
    // Update count display
    const countElement = starRating.querySelector('.oum_star_rating_count');
    if (countElement) {
      if (count > 0) {
        countElement.textContent = '(' + count + ')';
        countElement.style.display = 'inline';
      } else {
        countElement.style.display = 'none';
      }
    }
  }

  /**
   * Update the oum_all_locations data to keep star rating data in sync
   * @param {string} postId - The location post ID
   * @param {number} average - The average rating
   * @param {number} count - The rating count
   */
  function updateStarRatingLocationData(postId, average, count) {
    if (typeof window.oum_all_locations !== 'undefined' && Array.isArray(window.oum_all_locations)) {
      const locationIndex = window.oum_all_locations.findIndex(loc => loc.post_id === postId);
      if (locationIndex !== -1) {
        window.oum_all_locations[locationIndex].star_rating_avg = average;
        window.oum_all_locations[locationIndex].star_rating_count = count;
      }
    }
    if (typeof OUMMarkers !== 'undefined' && OUMMarkers.invalidateBubbleHtmlCacheForPost) {
      OUMMarkers.invalidateBubbleHtmlCacheForPost(postId);
    }
  }

  // Public interface
  return {
    init: function () {
      initializeVoteButtons();
    },
    handleVoteClick: handleVoteClick,
    updateVoteButton: updateVoteButton,
    updateVoteButtonStates: updateVoteButtonStates,
    refreshVoteCounts: refreshVoteCounts,
    initializeVoteCountsFromData: initializeVoteCountsFromData,
    updateLocationData: updateLocationData,
    initializeStarRatings: initializeStarRatings,
    updateStarRatingDisplay: updateStarRatingDisplay
  };
})();

// Initialize when DOM is ready
jQuery(document).ready(function($) {
  OUMVoteHandler.init();
});

// Export for global access (for compatibility with existing code)
window.OUMVoteHandler = OUMVoteHandler; 