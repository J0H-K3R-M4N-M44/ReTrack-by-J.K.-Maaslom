# Google Maps tracking interface

This is a prototype integration in `demo-prototype/`, not the live PHP customer portal. The map shows the participating computer-service locations in the Sogod demo and a selected repair's recorded branch/transfer state. It does not follow a courier live.

Load a local Maps key through the ignored `demo-prototype/maps-config.js`, using `maps-config.example.js` as a template. Restrict any Google key to the demo site's referrers and the Maps JavaScript API. Do not commit a working key. If no key is configured, the page displays a clear fallback with links to each branch in Google Maps.

The map panels have a visible text key for the branch locations, and transfers identify the last confirmed branch and destination separately. The key stays readable when map tiles fail to load. Map gestures cooperate with page scrolling on touch screens, and the map and markers are keyboard accessible. The layout grows on desktop and remains usable on narrow screens.

Customer status updates continue to come from the repair record; a marker is a shop or last-confirmed custody point. Real routes, GPS consent, geocoding, delivery events and SMS/email remain future backend work.
