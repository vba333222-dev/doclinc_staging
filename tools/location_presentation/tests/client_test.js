const fs = require('fs');
const vm = require('vm');

let passed = 0;
let failed = 0;

function expect(condition, name) {
	if (condition) {
		passed += 1;
		console.log('PASS ' + name);
	} else {
		failed += 1;
		console.log('FAIL ' + name);
	}
}

const window = {
	setTimeout,
	clearTimeout,
	fetch: null
};
const context = vm.createContext({ window, Map, Promise, Number, String, Object, Array, Error, AbortController, encodeURIComponent });
vm.runInContext(fs.readFileSync('assets/js/doclinc-location-address.js', 'utf8'), context);
const api = window.DoclincLocationAddress;

async function run() {
	expect(!!api && typeof api.create === 'function', 'location_presenter_exported');
	expect(api.normalizeLocation({ lat: '-6.01', lng: '106.05' }).lat === -6.01, 'valid_coordinate_normalized');
	expect(api.normalizeLocation({ lat: '91', lng: '106.05' }) === null, 'invalid_coordinate_rejected');
	expect(api.unavailable(true).address === 'Alamat belum dapat dikenali', 'human_safe_address_fallback');
	expect(!/[-+]?\d+\.\d+/.test(api.unavailable(true).address), 'fallback_contains_no_coordinate');

	const mapbox = api.fromMapbox({ features: [{
		id: 'address.1',
		place_name: 'Jalan Sultan Ageng Tirtayasa No. 1, Cilegon, Banten, Indonesia',
		text: 'Jalan Sultan Ageng Tirtayasa',
		context: [{ id: 'locality.cilegon', text: 'Cilegon' }, { id: 'region.banten', text: 'Banten' }]
	}] });
	expect(mapbox.available && mapbox.address.indexOf('Jalan Sultan Ageng') === 0, 'mapbox_precise_address_presented');
	expect(mapbox.locality === 'Cilegon', 'mapbox_locality_presented');

	const google = api.fromGoogle([{
		formatted_address: 'Jalan Jenderal Sudirman, Cilegon, Banten, Indonesia',
		address_components: [
			{ long_name: 'Kecamatan Purwakarta', types: ['administrative_area_level_3'] },
			{ long_name: 'Kota Cilegon', types: ['administrative_area_level_2'] }
		]
	}], 'OK');
	expect(google.available && google.provider === 'google', 'google_address_presented');
	expect(google.locality === 'Kecamatan Purwakarta', 'google_most_precise_locality_presented');

	const mapUrl = api.mapUrl({ latitude: -6.01, longitude: 106.05 });
	expect(mapUrl.indexOf('https://www.openstreetmap.org/') === 0, 'map_link_uses_https_openstreetmap');
	expect(api.mapUrl({ latitude: 'invalid', longitude: 106.05 }) === '', 'invalid_map_link_rejected');

	let fetchCount = 0;
	let lastUrl = '';
	const resolver = api.create({
		provider: 'mapbox',
		mapboxToken: 'public-test-token',
		minIntervalMs: 1,
		fetch: function (url) {
			fetchCount += 1;
			lastUrl = url;
			return Promise.resolve({ ok: true, json: function () { return Promise.resolve({ features: [{ place_name: 'Cilegon, Banten', text: 'Cilegon', context: [] }] }); } });
		}
	});
	const first = await resolver.resolve({ lat: -6.01, lng: 106.05 });
	const second = await resolver.resolve({ lat: -6.010001, lng: 106.050001 });
	expect(first.address === 'Cilegon, Banten' && second.address === first.address, 'reverse_geocode_result_returned');
	expect(fetchCount === 1, 'coordinate_cell_cache_prevents_duplicate_request');
	expect(lastUrl.indexOf('language=id') !== -1 && lastUrl.indexOf('limit=1') !== -1, 'mapbox_request_is_bounded_and_indonesian');
	expect(lastUrl.indexOf('public-test-token') !== -1, 'mapbox_public_token_used');

	const failingResolver = api.create({
		provider: 'mapbox',
		mapboxToken: 'public-test-token',
		minIntervalMs: 1,
		fetch: function () { return Promise.resolve({ ok: false }); }
	});
	const failedResult = await failingResolver.resolve({ lat: -6.02, lng: 106.06 });
	expect(failedResult.available === false && failedResult.address === 'Alamat belum dapat dikenali', 'provider_failure_is_safe_and_human');

	const noProvider = await api.create({ provider: '', minIntervalMs: 1 }).resolve({ lat: -6.02, lng: 106.06 });
	expect(noProvider.available === false && noProvider.location_found === true, 'missing_provider_preserves_location_state_without_coordinates');

	console.log('LOCATION_PRESENTATION_CLIENT_PASSED=' + passed);
	console.log('LOCATION_PRESENTATION_CLIENT_FAILED=' + failed);
	process.exit(failed === 0 ? 0 : 1);
}

run().catch(function (error) {
	console.error(error);
	process.exit(1);
});
