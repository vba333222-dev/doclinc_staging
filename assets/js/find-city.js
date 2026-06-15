const lokasi = {
	lat: -6.0176,
	lng: 106.053,
}; // Contoh koordinat Cilegon

const geocoders = new google.maps.Geocoder();
geocoders.geocode(
	{
		location: lokasi,
	},
	function (results, status) {
		if (status === "OK") {
			if (results[0]) {
				const components = results[0].address_components;
				const city =
					components.find((c) => c.types.includes("locality")) ||
					components.find((c) =>
						c.types.includes("administrative_area_level_2")
					);
				console.log(city);
				document.getElementById("kota").textContent = city.long_name;
			} else {
				document.getElementById("kota").textContent =
					"Tidak ada hasil geocoding";
			}
		} else {
			document.getElementById("kota").textContent = "Geocoder gagal: " + status;
		}
	}
);
