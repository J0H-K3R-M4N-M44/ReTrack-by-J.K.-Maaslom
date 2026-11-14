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
  return `<div class="repairtrack-map map-canvas" id="${esc(mapId)}"
    data-branches="${encodedBranches}"
    data-current="${esc(options.current || '')}"
    data-destination="${esc(options.destination || '')}"
    data-in-transit="${options.inTransit ? 'true' : 'false'}"
    aria-label="Map showing branch locations"></div>`;
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
  const mapElements = [...document.querySelectorAll('[data-branches]')];
  if (!mapElements.length) return;

  const config = window.REPAIRTRACK_MAPS_CONFIG || {};
  if (!config.apiKey) {
    mapElements.forEach((element) => {
      element.classList.add('map-fallback');
      element.textContent = 'Map preview is off. Add a restricted Google Maps key to maps-config.js, or use Open in Google Maps.';
    });
    return;
  }

  loadGoogleMaps(config.apiKey)
    .then(async () => {
      const [{ Map }, { AdvancedMarkerElement }, { InfoWindow }] = await Promise.all([
        google.maps.importLibrary('maps'),
        google.maps.importLibrary('marker'),
        google.maps.importLibrary('maps'),
      ]);

      mapElements.forEach((element) => {
        const branchNames = element.dataset.branches
          .split(',')
          .map(decodeURIComponent)
          .filter((name) => branchPlaces[name]);
        const current = element.dataset.current;
        const destination = element.dataset.destination;
        const places = branchNames.map((name) => ({ name, ...branchPlaces[name] }));
        if (!places.length) return;

        const map = new Map(element, {
          center: places[0].position,
          zoom: 14,
          mapId: config.mapId || 'DEMO_MAP_ID',
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: true,
        });
        const bounds = new google.maps.LatLngBounds();
        const info = new InfoWindow();

        places.forEach((place) => {
          const isCurrent = place.name === current;
          const isDestination = place.name === destination;
          const markerLabel = isCurrent ? 'Last confirmed' : isDestination ? 'Destination' : 'Branch';
          const markerContent = document.createElement('div');
          markerContent.className = `repairtrack-map-pin${isCurrent ? ' is-current' : ''}${isDestination ? ' is-destination' : ''}`;
          markerContent.textContent = markerLabel;

          const marker = new AdvancedMarkerElement({
            map,
            position: place.position,
            title: `${markerLabel}: ${place.name}`,
            content: markerContent,
          });
          marker.addListener('click', () => {
            const details = document.createElement('div');
            const title = document.createElement('strong');
            const address = document.createElement('p');
            title.textContent = `${markerLabel}: ${place.name}`;
            address.textContent = `${place.address}, Philippines`;
            details.append(title, address);
            info.setContent(details);
            info.open({ map, anchor: marker });
          });
          bounds.extend(place.position);
        });

        if (places.length > 1) {
          map.fitBounds(bounds);
        } else {
          map.setZoom(15);
        }
      });
    })
    .catch(() => {
      mapElements.forEach((element) => {
        element.classList.add('map-fallback');
        element.textContent = 'Google Maps could not load. Check the API key, enabled APIs, billing, and allowed website address.';
      });
    });
}

let googleMapsPromise;
function loadGoogleMaps(apiKey) {
  if (window.google?.maps?.importLibrary) return Promise.resolve();
  if (googleMapsPromise) return googleMapsPromise;

  googleMapsPromise = new Promise((resolve, reject) => {
    window.repairTrackMapsLoaded = resolve;
    const script = document.createElement('script');
    const params = new URLSearchParams({
      key: apiKey,
      v: 'weekly',
      loading: 'async',
      callback: 'repairTrackMapsLoaded',
    });
    const timeout = setTimeout(() => reject(new Error('Google Maps timed out')), 12000);
    window.repairTrackMapsLoaded = () => {
      clearTimeout(timeout);
      resolve();
    };
    script.src = `https://maps.googleapis.com/maps/api/js?${params.toString()}`;
    script.async = true;
    script.onerror = () => reject(new Error('Google Maps failed to load'));
    document.head.appendChild(script);
  });
  return googleMapsPromise;
}
