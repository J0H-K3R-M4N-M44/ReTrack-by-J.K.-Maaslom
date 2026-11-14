# Maps and demo alerts

## Turn on Google Maps

The maps work in the contact page, the customer portal's **Location** tab, and the staff **Map and custody** page. The demo uses two public Sogod shop listings as geographic examples for fictional RepairTrack branches. RepairTrack is not claiming an affiliation with either shop.

1. In Google Cloud, enable **Maps JavaScript API**, attach a billing account, and create a browser API key.
2. Restrict the key to the Maps JavaScript API and to the local web address you use, such as `http://localhost:5500/*` for VS Code Live Server or `http://localhost:8080/*` for PHP's built-in server. Use the exact host and port shown in your browser.
3. Copy `maps-config.example.js` to `maps-config.js` and paste the restricted browser key into `apiKey`. The local key file is ignored by Git.
4. Serve the folder using VS Code Live Server or PHP, then open the site's HTTP address. Maps JavaScript API needs a website origin; opening the page as `file://` only shows the fallback link.

The map uses Google's `DEMO_MAP_ID` for the sample view. For a deployed site, create a map ID in Google Cloud and set it in the local config. Never put a server key or an unrestricted key in a browser file. Google Maps sends the visitor's browser request to Google when an enabled map loads.

If you have no Google key, the page still shows the branch address and an **Open in Google Maps** link.

## What “tracking” means in this demo

The marker represents the last branch where staff confirmed custody. A staff handoff updates the location. While a device is in transit, the map can show its last confirmed branch and intended destination, but it does not show the courier's current position. The demo does not request phone location or collect GPS coordinates. A real-time courier map would need a separate consent-based mobile location service and server-side location updates.

The staff **Map and custody** page filters its device list using the selected branch. Admins can select all branches. The customer portal shows only the selected repair and its public location events.

## Sogod location references

- Ormocnet lists its Sogod store at Gaisano Capital, Zone 5, Sogod: <https://ormocnet.com/contact-us/>.
- Greenware Sogod appears in a public business listing at Bacadar Realty, Rizal Street, Zone 5, Sogod. Its map coordinate comes from a directory listing and should be confirmed by the shop before operational use: <https://philippines.worldplaces.me/review/174345244-greenware-sogod.html>.
- Coordinates are reference points for the demo map. They do not prove that a RepairTrack branch exists there.

## Test the map flow

1. Open **Contacts** and check that both public shop markers appear.
2. Track `DEMO-1042`, select **Location**, and review the last confirmed branch and public handoff timeline.
3. Sign in as Admin or Collector and open **Map and custody**. Change the branch filter and compare the device list.
4. Record a transfer in the demo and confirm its handoff. The map should continue to describe the last confirmed branch and destination. It must not imply that it follows the courier between updates.

## Demo email and SMS alerts

Track `DEMO-1042`, open **Messages**, verify with the demo PIN `2468`, then select Email or SMS under Alerts. Switch to the assigned technician, open that repair, and publish a valid stage change or a quotation. Track the same ticket again and verify to see the alert. Internal notes do not generate external alerts.

Both channels preview automatically by default. For real email during a local presentation, configure PHP mail with a working mail transport, set the server environment variable `REPAIRTRACK_DEMO_EMAIL` to your own test inbox, enable `USE_LOCAL_EMAIL_RELAY` in `ticket-alerts.js`, and serve this directory using PHP on localhost. Email goes only to that configured inbox. Mail acceptance does not confirm delivery. SMS is simulated. Never place provider secrets in browser scripts.
