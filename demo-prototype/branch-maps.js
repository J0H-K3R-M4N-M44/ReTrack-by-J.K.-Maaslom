// Public shop listings are geographic examples for fictional RepairTrack branches.
const branchPlaces = {
  'Central Branch': {
    name: 'Greenware Customized Systems & PC Accessories',
    address: 'Unit 4, Bacadar Realty Corp. Building, Rizal Street, Zone 5, Sogod, Southern Leyte',
    position: { lat: 10.38833, lng: 124.98095 },
    source: 'Public business listing',
  },
  'North Branch': {
    name: 'Ormocnet Sogod',
    address: 'Gaisano Capital, Zone 5, Sogod, Southern Leyte',
    position: { lat: 10.38503, lng: 124.98523 },
    source: 'Ormocnet contact page',
  },
};

// Prepare one map surface. A local key file supplies the key without committing it.
function branchMapPanel(branches, options = {}) {
  const visibleBranches = [...new Set(branches)].filter((name) => branchPlaces[name]);
  if (!visibleBranches.length) return '';

  const mapId = options.id || 'repairtrack-branch-map';
  const encodedBranches = visibleBranches.map(encodeURIComponent).join(',');
  const current = options.current || '';
  const destination = options.destination || '';
  const hasTransfer = Boolean(current && destination && options.inTransit);
  const mapLabel = hasTransfer
    ? `Map showing last confirmed location at ${current} and destination ${destination}`
    : `Map showing ${visibleBranches.join(' and ')}`;
  const locationList = visibleBranches.map((branch) => {
    const place = branchPlaces[branch];
    const isCurrent = branch === current;
    const isDestination = hasTransfer && branch === destination;
    const status = isCurrent ? 'Last confirmed' : isDestination ? 'Destination' : 'Branch';
    const statusClass = isCurrent ? 'is-current' : isDestination ? 'is-destination' : '';

    return `<li class="map-location ${statusClass}">
      <span class="map-location-dot" aria-hidden="true"></span>
      <span><strong>${esc(status)} · ${esc(branch)}</strong><small>${esc(place.name)}</small></span>
    </li>`;
  }).join('');

  return `<div class="map-widget">
    <div class="repairtrack-map map-canvas" id="${esc(mapId)}"
      data-branches="${encodedBranches}"
      data-current="${esc(current)}"
      data-destination="${esc(destination)}"
      data-in-transit="${options.inTransit ? 'true' : 'false'}"
      role="region" tabindex="0" aria-label="${esc(mapLabel)}"></div>
    ${visibleBranches.length > 1 ? `<ul class="map-location-list" aria-label="Map location key">${locationList}</ul>` : ''}
    <p class="map-disclaimer">Pins show shop locations and confirmed handoffs. They do not show live GPS.</p>
  </div>`;
}

// Keep a Google Maps link available even when no API key has been configured.
function branchMapCard(branch, options = {}) {
  if (typeof options !== 'object' || options === null) options = {};
  const place = branchPlaces[branch];
  if (!place) return '';

  const current = options.current || '';
  const branches = options.branches || [branch];
  const address = `${place.address}, Philippines`;
  const query = encodeURIComponent(`${place.name}, ${address}`);
  const searchUrl = `https://www.google.com/maps/search/?api=1&query=${query}`;
  const transitText = options.statusText || (options.inTransit
    ? `In transit to ${options.destination || 'the receiving location'}. Last confirmed at ${current}. Live GPS is not available in this demo.`
    : current
      ? `Last confirmed at ${current}. The map updates after staff records a handoff.`
      : 'Public shop listing used as a geographic reference for this demo.');
  const mapOptions = {
    id: options.id || `repairtrack-map-${branch.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`,
    branches,
    current,
    destination: options.destination || '',
    inTransit: Boolean(options.inTransit),
  };

  return `<article class="card map-card">
    <h3>${esc(branch)}</h3>
    <p>${esc(place.name)}</p>
    <p class="muted">${esc(address)}</p>
    <p class="map-status">${esc(options.statusText || transitText)}</p>
    ${branchMapPanel(mapOptions.branches, mapOptions)}
    <small class="muted">Public shop listing used as a location reference. No affiliation is implied.</small>
    <a class="button quiet" href="${searchUrl}" target="_blank" rel="noopener noreferrer">Open in Google Maps</a>
  </article>`;
}

// Add a map only when the current page has a map panel and a browser key is configured.
function initRepairTrackMaps() {
  const mapElements = [...document.querySelectorAll('.repairtrack-map[data-branches]')];
  if (!mapElements.length) return;

  const config = window.REPAIRTRACK_MAPS_CONFIG || {};
  if (!config.apiKey || location.protocol === 'file:') {
    mapElements.forEach((element) => {
      showMapFallback(element, 'Interactive map unavailable. You can still view these branch locations in Google Maps.');
    });
    return;
  }

  loadGoogleMaps(config.apiKey)
    .then(async () => {
      const [{ Map, InfoWindow }, { AdvancedMarkerElement }] = await Promise.all([
        google.maps.importLibrary('maps'),
        google.maps.importLibrary('marker'),
      ]);

      mapElements.forEach((element) => {
        // Navigation may have replaced this panel while Google was loading.
        if (!element.isConnected || element.dataset.mapReady === 'true') return;
        element.classList.remove('map-fallback');
        element.replaceChildren();
        const branchNames = element.dataset.branches
          .split(',')
          .map(decodeURIComponent)
          .filter((name) => branchPlaces[name]);
        const current = element.dataset.current;
        const destination = element.dataset.destination;
        const places = branchNames.map((name) => ({ ...branchPlaces[name], branchName: name }));
        if (!places.length) return;

        const map = new Map(element, {
          center: places[0].position,
          zoom: 14,
          mapId: config.mapId || 'DEMO_MAP_ID',
          // Let the page scroll naturally on touch screens; keep keyboard use available.
          gestureHandling: 'cooperative',
          keyboardShortcuts: true,
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: true,
          clickableIcons: false,
        });
        const bounds = new google.maps.LatLngBounds();
        const info = new InfoWindow();

        places.forEach((place) => {
          const isCurrent = place.branchName === current;
          const isDestination = place.branchName === destination;
          const markerLabel = isCurrent ? 'Last confirmed' : isDestination ? 'Destination' : 'Branch';
          const markerContent = document.createElement('div');
          markerContent.className = `repairtrack-map-pin${isCurrent ? ' is-current' : ''}${isDestination ? ' is-destination' : ''}`;
          markerContent.textContent = markerLabel;

          const marker = new AdvancedMarkerElement({
            map,
            position: place.position,
            title: `${markerLabel}: ${place.branchName} · ${place.name}`,
            content: markerContent,
            gmpClickable: true,
          });
          marker.addEventListener('gmp-click', () => {
            const details = document.createElement('div');
            details.className = 'map-info';
            const title = document.createElement('strong');
            const address = document.createElement('p');
            title.textContent = `${markerLabel}: ${place.branchName} · ${place.name}`;
            address.textContent = `${place.address}, Philippines`;
            details.append(title, address);
            info.setContent(details);
            info.open({ map, anchor: marker });
          });
          bounds.extend(place.position);
        });

        element.dataset.mapReady = 'true';
        if (places.length > 1) {
          map.fitBounds(bounds);
        } else {
          map.setZoom(15);
        }
      });
    })
    .catch(() => {
      mapElements.forEach((element) => {
        if (element.isConnected) {
          showMapFallback(element, 'The map could not load. Use a branch link below to view its location.');
        }
      });
    });
}

// Every map, including the staff overview, has useful links if loading fails.
function showMapFallback(element, message) {
  element.classList.add('map-fallback');
  element.removeAttribute('data-map-ready');
  const text = document.createElement('p');
  text.textContent = message;
  const links = document.createElement('div');
  links.className = 'actions';
  element.dataset.branches.split(',').map(decodeURIComponent).forEach((branch) => {
    const place = branchPlaces[branch];
    if (!place) return;
    const link = document.createElement('a');
    link.className = 'button quiet';
    link.textContent = `View ${branch}`;
    link.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(place.name + ', ' + place.address)}`;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    links.append(link);
  });
  element.replaceChildren(text, links);
}

let googleMapsPromise;
function loadGoogleMaps(apiKey) {
  if (window.google?.maps?.importLibrary) return Promise.resolve();
  if (googleMapsPromise) return googleMapsPromise;

  googleMapsPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    const params = new URLSearchParams({
      key: apiKey,
      v: 'weekly',
      loading: 'async',
      callback: 'repairTrackMapsLoaded',
    });
    // Clear failed requests so returning to the page can try again.
    const fail = () => {
      clearTimeout(timeout);
      script.remove();
      reject(new Error('Google Maps failed to load'));
    };
    const timeout = setTimeout(fail, 12000);
    window.repairTrackMapsLoaded = () => {
      clearTimeout(timeout);
      resolve();
    };
    window.gm_authFailure = () => {
      fail();
      document.querySelectorAll('.repairtrack-map[data-branches]').forEach((element) => {
        showMapFallback(element, 'Interactive map unavailable. Use a branch link to view its location.');
      });
    };
    script.src = `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
    script.async = true;
    script.onerror = fail;
    document.head.appendChild(script);
  }).catch((error) => {
    googleMapsPromise = null;
    throw error;
  });
  return googleMapsPromise;
}
