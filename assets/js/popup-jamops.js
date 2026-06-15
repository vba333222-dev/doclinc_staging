document.addEventListener("DOMContentLoaded", function () {
	const lastPopupTime = localStorage.getItem("lastPopupTime");
	const currentTime = new Date().getTime();

	if (!lastPopupTime || currentTime - lastPopupTime > 3600000) {
		// 1 hour = 3600000 ms
		Swal.fire({
			title: "Informasi",
			text: "Jam operasional kunjungan nakes dari 08.00 s/d 17.00",
			icon: "info",
			confirmButtonText: "OK",
		}).then(() => {
			localStorage.setItem("lastPopupTime", currentTime.toString());
		});
	}
});
