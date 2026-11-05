// Public shop listings serve as examples for two fictional RepairTrack branches.
const GOOGLE_MAPS_EMBED_KEY = ''; // Enable Maps Embed API and restrict this browser key to your site.
const branchPlaces = {
    'Central Branch': ['Golly Technology Center and Computer Repair Services', 'Bacadar Realty Building, Rizal Street, Zone 5'],
    'North Branch': ['Ormocnet Sogod', 'Gaisano Capital, Bulwang Street, Zone 5']
};

function branchMapCard(branch) {
    const place = branchPlaces[branch];
    if (!place) return '';
    const address = place[1] + ', Sogod, Southern Leyte, Philippines';
    const query = encodeURIComponent(place[0] + ', ' + address);
    const searchUrl = 'https://www.google.com/maps/search/?api=1&query=' + query;
    let map = '';
    if (GOOGLE_MAPS_EMBED_KEY && /^https?:$/.test(location.protocol)) {
        const src = 'https://www.google.com/maps/embed/v1/place?key=' + encodeURIComponent(GOOGLE_MAPS_EMBED_KEY) + '&q=' + query;
        map = '<iframe title="Map of ' + place[0] + '" width="100%" height="230" style="border:0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" src="' + src + '"></iframe>';
    }
    return '<article class="card"><h3>' + branch + '</h3><p>Simulated branch near ' + place[0] + '</p><p class="muted">' + address + '</p>' + map + '<a class="button quiet" href="' + searchUrl + '" target="_blank" rel="noopener noreferrer">Open in Google Maps</a></article>';
}