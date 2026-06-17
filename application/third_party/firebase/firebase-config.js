const firebaseConfig = {
	apiKey: "AIzaSyDHxk8DvefHVrlfk53rEtAnA7pDlEDFYws",
	authDomain: "otp-sms-cilegon.firebaseapp.com",
	databaseURL:
		"https://otp-sms-cilegon-default-rtdb.asia-southeast1.firebasedatabase.app",
	projectId: "otp-sms-cilegon",
	storageBucket: "otp-sms-cilegon.firebasestorage.app",
	messagingSenderId: "38755063807",
	appId: "1:38755063807:web:9b8f04e4386ac563b0f9ca",
	measurementId: "G-TLN917YK4C",
};

// Inisialisasi Firebase
firebase.initializeApp(firebaseConfig);

const messaging = firebase.messaging();

export { messaging };
