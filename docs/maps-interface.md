# Google Maps tracking interface

This is a prototype integration in `demo-prototype/`, not the live PHP customer portal. The map shows the participating computer-service locations in the Sogod demo and a selected repair's recorded branch/transfer state. It does not follow a courier live.

Load a local Maps key through the ignored `demo-prototype/maps-config.js`, using `maps-config.example.js` as a template. Restrict any Google key to the demo site's referrers and the Maps JavaScript API. Do not commit a working key. Without a key or network access, the page should keep the branch list and text directions usable.

The interface update should preserve keyboard access, a text alternative to the map, responsive list/detail layout and clear permission/location states. Customer status updates continue to come from the repair record; a marker animation is illustrative only. Real routes, GPS consent, geocoding, delivery events and SMS/email remain future backend work.
