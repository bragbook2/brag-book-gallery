/******/ (function() { // webpackBootstrap
/*!***********************************!*\
  !*** ./src/js/provider-finder.js ***!
  \***********************************/
/**
 * BRAG book Gallery — Find a Provider locator.
 *
 * Store-locator modal: a geo-based list of practices/providers with a Google
 * map. Built as a webpack entry (brag-book-gallery-provider-finder). Reads
 * config from window.bragBookProviderFinder.
 *
 * @since 4.6.0
 */
(function () {
  'use strict';

  var MAX_RESULTS = 10;
  var config = window.bragBookProviderFinder || {};
  var dialog = null;
  var map = null;
  var geocoder = null;
  var infoWindow = null;
  var markers = [];
  var practices = [];
  var currentOrdered = [];
  var loaded = false;
  var originMarker = null;
  var lastOrigin = null;

  /**
   * Selected search radius in miles (defaults to 25).
   */
  function getRadius() {
    var el = document.getElementById('bbProviderFinderRadius');
    var value = el ? parseFloat(el.value) : 25;
    return isFinite(value) ? value : 25;
  }

  /**
   * Run google-maps-dependent work once the API is ready.
   */
  function whenGoogleReady(callback) {
    if (window.google && window.google.maps) {
      callback();
      return;
    }
    var tries = 0;
    var timer = setInterval(function () {
      tries++;
      if (window.google && window.google.maps) {
        clearInterval(timer);
        callback();
      } else if (tries > 60) {
        clearInterval(timer);
      }
    }, 150);
  }

  /**
   * Distance in miles between two {lat,lng} points.
   */
  function distanceMiles(a, b) {
    if (window.google && google.maps && google.maps.geometry) {
      var meters = google.maps.geometry.spherical.computeDistanceBetween(new google.maps.LatLng(a.lat, a.lng), new google.maps.LatLng(b.lat, b.lng));
      return meters / 1609.344;
    }
    // Haversine fallback.
    var toRad = function (d) {
      return d * Math.PI / 180;
    };
    var R = 3958.8;
    var dLat = toRad(b.lat - a.lat);
    var dLng = toRad(b.lng - a.lng);
    var lat1 = toRad(a.lat);
    var lat2 = toRad(b.lat);
    var h = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.sin(dLng / 2) * Math.sin(dLng / 2) * Math.cos(lat1) * Math.cos(lat2);
    return R * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
  }
  function escapeHtml(value) {
    var div = document.createElement('div');
    div.textContent = value == null ? '' : String(value);
    return div.innerHTML;
  }
  function practiceAddress(p) {
    if (p.address) {
      return p.address;
    }
    return [[p.city, p.state].filter(Boolean).join(', '), p.zip].filter(Boolean).join(' ');
  }

  /**
   * Compute the displayed list: filter by radius + sort by distance when an
   * origin is set, capped at MAX_RESULTS (numbered 1–10).
   */
  function getOrdered(origin) {
    var ordered = practices.slice();
    if (origin) {
      var radius = getRadius();
      ordered.forEach(function (p) {
        p._distance = p.lat != null && p.lng != null ? distanceMiles(origin, {
          lat: p.lat,
          lng: p.lng
        }) : Number.POSITIVE_INFINITY;
      });
      ordered = ordered.filter(function (p) {
        return p._distance <= radius;
      }).sort(function (a, b) {
        return a._distance - b._distance;
      });
    }
    return ordered.slice(0, MAX_RESULTS);
  }
  function providersHtml(p) {
    if (!Array.isArray(p.providers) || !p.providers.length) {
      return '';
    }
    var html = '<ul class="brag-book-gallery-provider-finder-providers">';
    p.providers.forEach(function (prov) {
      var avatar = prov.image ? '<img src="' + escapeHtml(prov.image) + '" alt="" width="24" height="24" />' : '<span class="brag-book-gallery-provider-finder-avatar--placeholder"></span>';
      html += '<li>' + avatar + '<span>' + escapeHtml(prov.name) + '</span></li>';
    });
    return html + '</ul>';
  }
  function metaHtml(p) {
    var meta = '';
    if (p.phone) {
      meta += '<a class="brag-book-gallery-provider-finder-phone" href="tel:' + escapeHtml(p.phone) + '">' + escapeHtml(p.phone) + '</a>';
    }
    if (p.website) {
      meta += '<a class="brag-book-gallery-provider-finder-website" href="' + escapeHtml(p.website) + '" target="_blank" rel="noopener">Website</a>';
    }
    if (Array.isArray(p.accreditations) && p.accreditations.length) {
      meta += '<span class="brag-book-gallery-provider-finder-accred">' + escapeHtml(p.accreditations.join(', ')) + '</span>';
    }
    return meta ? '<div class="brag-book-gallery-provider-finder-meta">' + meta + '</div>' : '';
  }

  /**
   * Render the numbered practice cards.
   */
  function renderList(ordered, origin) {
    var listEl = document.getElementById('bbProviderFinderList');
    if (!listEl) {
      return;
    }
    if (!ordered.length) {
      var emptyMsg = origin ? 'No practices within ' + getRadius() + ' miles.' : 'No practices found.';
      listEl.innerHTML = '<p class="brag-book-gallery-provider-finder-empty">' + emptyMsg + '</p>';
      return;
    }
    var html = '';
    ordered.forEach(function (p, i) {
      var address = practiceAddress(p);
      var distance = origin && isFinite(p._distance) ? '<span class="brag-book-gallery-provider-finder-distance">' + p._distance.toFixed(1) + ' mi</span>' : '';
      html += '<button type="button" class="brag-book-gallery-provider-finder-item" data-practice-id="' + p.id + '">' + '<span class="brag-book-gallery-provider-finder-rank">' + (i + 1) + '</span>' + '<span class="brag-book-gallery-provider-finder-item-body">' + '<span class="brag-book-gallery-provider-finder-item-head">' + '<span class="brag-book-gallery-provider-finder-name">' + escapeHtml(p.name) + '</span>' + distance + '</span>' + (address ? '<span class="brag-book-gallery-provider-finder-address">' + escapeHtml(address) + '</span>' : '') + metaHtml(p) + providersHtml(p) + '</span>' + '</button>';
    });
    listEl.innerHTML = html;
  }

  /**
   * Info window content shown when a pin is clicked.
   */
  function infoWindowHtml(p, index) {
    var address = practiceAddress(p);
    var html = '<div class="brag-book-gallery-provider-finder-infowindow">' + '<strong>' + (index + 1) + '. ' + escapeHtml(p.name) + '</strong>';
    if (address) {
      html += '<div>' + escapeHtml(address) + '</div>';
    }
    if (p.phone) {
      html += '<div><a href="tel:' + escapeHtml(p.phone) + '">' + escapeHtml(p.phone) + '</a></div>';
    }
    if (p.website) {
      html += '<div><a href="' + escapeHtml(p.website) + '" target="_blank" rel="noopener">Website</a></div>';
    }
    if (Array.isArray(p.providers) && p.providers.length) {
      html += '<div>' + escapeHtml(p.providers.map(function (pr) {
        return pr.name;
      }).join(', ')) + '</div>';
    }
    return html + '</div>';
  }
  function clearMarkers() {
    markers.forEach(function (m) {
      m.setMap(null);
    });
    markers = [];
  }

  /**
   * Render numbered pins matching the card order; clicking a pin opens its
   * details and highlights the card.
   */
  function renderMarkers(ordered) {
    if (!map) {
      return;
    }
    if (!infoWindow) {
      infoWindow = new google.maps.InfoWindow();
    }
    clearMarkers();
    var bounds = new google.maps.LatLngBounds();
    var placed = 0;
    ordered.forEach(function (p, i) {
      if (p.lat == null || p.lng == null) {
        return;
      }
      var position = {
        lat: p.lat,
        lng: p.lng
      };
      var marker = new google.maps.Marker({
        position: position,
        map: map,
        title: p.name,
        label: {
          text: String(i + 1),
          color: '#ffffff',
          fontWeight: '700',
          fontSize: '12px'
        }
      });
      marker._practiceId = p.id;
      marker.addListener('click', function () {
        infoWindow.setContent(infoWindowHtml(p, i));
        infoWindow.open(map, marker);
        focusPractice(p.id, false);
      });
      markers.push(marker);
      bounds.extend(position);
      placed++;
    });
    if (placed > 0) {
      map.fitBounds(bounds);
      if (placed === 1) {
        map.setZoom(12);
      }
    }
  }

  /**
   * Highlight a practice in the list and (optionally) pan the map to it.
   */
  function focusPractice(id, pan) {
    document.querySelectorAll('.brag-book-gallery-provider-finder-item').forEach(function (item) {
      var active = parseInt(item.dataset.practiceId, 10) === id;
      item.classList.toggle('is-active', active);
      if (active) {
        item.scrollIntoView({
          block: 'nearest',
          behavior: 'smooth'
        });
      }
    });
    var marker = markers.find(function (m) {
      return m._practiceId === id;
    });
    if (map && marker) {
      if (pan) {
        map.panTo(marker.getPosition());
        map.setZoom(Math.max(map.getZoom(), 12));
      }
      if (infoWindow) {
        var p = currentOrdered.find(function (pr) {
          return pr.id === id;
        });
        var idx = currentOrdered.indexOf(p);
        if (p) {
          infoWindow.setContent(infoWindowHtml(p, idx));
          infoWindow.open(map, marker);
        }
      }
    }
  }

  /**
   * Refresh both the list and the pins for the current origin/radius.
   */
  function refresh(origin) {
    currentOrdered = getOrdered(origin);
    renderList(currentOrdered, origin);
    renderMarkers(currentOrdered);
  }

  /**
   * Initialise the map, then render the pins for the current list.
   */
  function initMap() {
    var mapEl = document.getElementById('bbProviderFinderMap');
    if (!mapEl) {
      return;
    }
    geocoder = new google.maps.Geocoder();
    map = new google.maps.Map(mapEl, {
      zoom: 4,
      center: {
        lat: 39.5,
        lng: -98.35
      },
      mapTypeControl: false,
      streetViewControl: false
    });
    renderMarkers(currentOrdered);
  }

  /**
   * Fetch practices once, then render the list and pins.
   */
  function loadPractices() {
    if (loaded) {
      return;
    }
    loaded = true;
    var listEl = document.getElementById('bbProviderFinderList');
    if (listEl) {
      listEl.innerHTML = '<p class="brag-book-gallery-provider-finder-empty">Loading…</p>';
    }
    var body = new FormData();
    body.append('action', config.action || 'brag_book_get_practices');
    body.append('nonce', config.nonce || '');
    fetch(config.ajaxUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json();
    }).then(function (json) {
      if (!json || !json.success || !json.data || !Array.isArray(json.data.practices)) {
        if (listEl) {
          listEl.innerHTML = '<p class="brag-book-gallery-provider-finder-empty">No practices found.</p>';
        }
        return;
      }
      practices = json.data.practices;
      currentOrdered = getOrdered(null);
      renderList(currentOrdered, null);
      if (config.hasMap) {
        whenGoogleReady(initMap);
      }
    }).catch(function () {
      if (listEl) {
        listEl.innerHTML = '<p class="brag-book-gallery-provider-finder-empty">Unable to load practices.</p>';
      }
    });
  }

  /**
   * Recenter from a {lat,lng} origin: drop an origin marker and re-rank.
   */
  function applyOrigin(origin) {
    lastOrigin = origin;
    refresh(origin);
    if (!map) {
      return;
    }
    if (originMarker) {
      originMarker.setMap(null);
    }
    originMarker = new google.maps.Marker({
      position: origin,
      map: map,
      title: 'Your location',
      icon: {
        path: google.maps.SymbolPath.CIRCLE,
        scale: 7,
        fillColor: '#1a73e8',
        fillOpacity: 1,
        strokeColor: '#ffffff',
        strokeWeight: 2
      },
      zIndex: 999
    });
  }
  function handleSearch() {
    var input = document.getElementById('bbProviderFinderSearch');
    var query = input ? input.value.trim() : '';
    if (!query || !config.hasMap) {
      return;
    }
    whenGoogleReady(function () {
      if (!geocoder) {
        geocoder = new google.maps.Geocoder();
      }
      geocoder.geocode({
        address: query
      }, function (results, status) {
        if (status === 'OK' && results[0]) {
          var loc = results[0].geometry.location;
          applyOrigin({
            lat: loc.lat(),
            lng: loc.lng()
          });
        }
      });
    });
  }
  function handleLocate() {
    if (!navigator.geolocation) {
      return;
    }
    navigator.geolocation.getCurrentPosition(function (pos) {
      var origin = {
        lat: pos.coords.latitude,
        lng: pos.coords.longitude
      };
      if (config.hasMap) {
        whenGoogleReady(function () {
          applyOrigin(origin);
        });
      } else {
        lastOrigin = origin;
        refresh(origin);
      }
    });
  }

  /**
   * Reset the search: clear the input/origin and show all practices again.
   */
  function handleReset() {
    var input = document.getElementById('bbProviderFinderSearch');
    if (input) {
      input.value = '';
    }
    lastOrigin = null;
    if (originMarker) {
      originMarker.setMap(null);
      originMarker = null;
    }
    if (infoWindow) {
      infoWindow.close();
    }
    refresh(null);
  }
  function openDialog() {
    if (!dialog) {
      return;
    }
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      dialog.setAttribute('open', 'open');
    }
    loadPractices();
    // Map needs a layout pass after the dialog becomes visible.
    if (map) {
      whenGoogleReady(function () {
        google.maps.event.trigger(map, 'resize');
        renderMarkers(currentOrdered);
      });
    }
  }
  function closeDialog() {
    if (!dialog) {
      return;
    }
    if (typeof dialog.close === 'function') {
      dialog.close();
    } else {
      dialog.removeAttribute('open');
    }
  }
  function init() {
    dialog = document.getElementById('findProviderDialog');
    if (!dialog) {
      return;
    }
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-action="find-provider"]')) {
        e.preventDefault();
        openDialog();
      } else if (e.target.closest('#findProviderDialog [data-action="close-dialog"]')) {
        e.preventDefault();
        closeDialog();
      } else if (e.target.closest('[data-action="provider-finder-search"]')) {
        e.preventDefault();
        handleSearch();
      } else if (e.target.closest('[data-action="provider-finder-locate"]')) {
        e.preventDefault();
        handleLocate();
      } else if (e.target.closest('[data-action="provider-finder-reset"]')) {
        e.preventDefault();
        handleReset();
      } else {
        var item = e.target.closest('.brag-book-gallery-provider-finder-item');
        if (item) {
          focusPractice(parseInt(item.dataset.practiceId, 10), true);
        }
      }
    });

    // Close when clicking the backdrop of the native dialog.
    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) {
        closeDialog();
      }
    });
    var searchInput = document.getElementById('bbProviderFinderSearch');
    if (searchInput) {
      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          handleSearch();
        }
      });
    }

    // Changing the radius re-filters the current results.
    var radiusSelect = document.getElementById('bbProviderFinderRadius');
    if (radiusSelect) {
      radiusSelect.addEventListener('change', function () {
        refresh(lastOrigin);
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
/******/ })()
;
//# sourceMappingURL=brag-book-gallery-provider-finder.js.map