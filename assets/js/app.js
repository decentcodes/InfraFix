console.log("InfraFix loaded.");

const locationButton = document.querySelector("#get-location");
const locationStatus = document.querySelector("#location-status");

if (locationButton && locationStatus) {
    locationButton.addEventListener("click", () => {

        if (!navigator.geolocation) {
            locationStatus.textContent =
                "Geolocation is not supported by this browser.";
            return;
        }

        locationStatus.textContent =
            "Requesting your location...";

        navigator.geolocation.getCurrentPosition(
            (position) => {

                const latitude = position.coords.latitude;
                const longitude = position.coords.longitude;

                locationStatus.textContent =
                    `Location detected: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;

                console.log("Latitude:", latitude);
                console.log("Longitude:", longitude);
            },

            (error) => {

                switch (error.code) {

                    case error.PERMISSION_DENIED:
                        locationStatus.textContent =
                            "Location permission was denied.";
                        break;

                    case error.POSITION_UNAVAILABLE:
                        locationStatus.textContent =
                            "Your location could not be determined.";
                        break;

                    case error.TIMEOUT:
                        locationStatus.textContent =
                            "Location request timed out.";
                        break;

                    default:
                        locationStatus.textContent =
                            "Unable to retrieve your location.";
                }
            }
        );
    });
}


const photoInput = document.querySelector("#issue-photo");
const photoPreview = document.querySelector("#photo-preview");
const photoStatus = document.querySelector("#photo-status");

if (photoInput && photoPreview && photoStatus) {
    photoInput.addEventListener("change", () => {

        photoPreview.innerHTML = "";
        photoStatus.textContent = "";

        const file = photoInput.files[0];

        if (!file) {
            return;
        }

        if (!file.type.startsWith("image/")) {
            photoStatus.textContent =
                "Please select an image file.";

            photoInput.value = "";
            return;
        }

        const image = document.createElement("img");

        image.src = URL.createObjectURL(file);
        image.alt = "Preview of selected issue photograph";

        photoPreview.appendChild(image);

        photoStatus.textContent =
            "Photo selected successfully.";
    });
}