console.log("InfraFix loaded.");

const reporterLatitudeInput =
    document.querySelector("#reporter-latitude");

const reporterLongitudeInput =
    document.querySelector("#reporter-longitude");

const reportedLatitudeInput =
    document.querySelector("#reported-latitude");

const reportedLongitudeInput =
    document.querySelector("#reported-longitude");

const locationButton =
    document.querySelector("#get-location");

const locationStatus =
    document.querySelector("#location-status");

const reportMap =
    document.querySelector("#report-map");

const reportForm =
    document.querySelector(".report-form");

let map = null;
let reporterMarker = null;
let issueMarker = null;
let verificationCircle = null;
let issueMarkers = [];


// --------------------------------------------------
// Update selected issue location
// --------------------------------------------------

function updateIssueLocation(latitude, longitude) {
    reportedLatitudeInput.value = latitude;
    reportedLongitudeInput.value = longitude;

    locationStatus.textContent =
        `Issue location selected: ${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
}


// --------------------------------------------------
// Initialize report map
// --------------------------------------------------

function initializeMap(latitude, longitude) {
    if (!map) {
        map = L.map("report-map").setView(
            [latitude, longitude],
            18
        );

        L.tileLayer(
            "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
            {
                attribution:
                    "&copy; OpenStreetMap contributors"
            }
        ).addTo(map);

        // Blue marker: reporter's current location
        reporterMarker = L.circleMarker(
            [latitude, longitude],
            {
                radius: 8,
                color: "#2563eb",
                fillColor: "#2563eb",
                fillOpacity: 1,
                weight: 2
            }
        ).addTo(map);

        reporterMarker.bindTooltip(
            "Your current location"
        );

        // 50 metre verification radius
        verificationCircle = L.circle(
            [latitude, longitude],
            {
                radius: 50,
                color: "#2563eb",
                fillColor: "#2563eb",
                fillOpacity: 0.08,
                weight: 2
            }
        ).addTo(map);

        // Red marker: issue location
        issueMarker = L.marker(
            [latitude, longitude],
            {
                draggable: true
            }
        ).addTo(map);

        issueMarker.bindTooltip(
            "Issue location"
        );

        issueMarker.on(
            "dragend",
            function () {
                const position =
                    issueMarker.getLatLng();

                updateIssueLocation(
                    position.lat,
                    position.lng
                );
            }
        );

    } else {
        reporterMarker.setLatLng(
            [latitude, longitude]
        );

        verificationCircle.setLatLng(
            [latitude, longitude]
        );

        issueMarker.setLatLng(
            [latitude, longitude]
        );

        map.setView(
            [latitude, longitude],
            18
        );
    }

    updateIssueLocation(
        latitude,
        longitude
    );

    loadNearbyIssues(
        latitude,
        longitude
    );
}


// --------------------------------------------------
// Load nearby Issues
// --------------------------------------------------

async function loadNearbyIssues(
    latitude,
    longitude
) {
    try {
        const response = await fetch(
            `../../api/issues-nearby.php?latitude=${encodeURIComponent(latitude)}&longitude=${encodeURIComponent(longitude)}`
        );

        const result =
            await response.json();

        if (!result.success) {
            console.error(
                "Could not load nearby issues:",
                result.message
            );

            return;
        }

        // Remove existing Issue markers
        issueMarkers.forEach(
            (marker) => {
                map.removeLayer(marker);
            }
        );

        issueMarkers = [];

        result.issues.forEach(
            (issue) => {

                const issueIcon =
                    L.divIcon({
                        className:
                            "existing-issue-marker",

                        html:
                            "<div></div>",

                        iconSize:
                            [18, 18],

                        iconAnchor:
                            [9, 9],

                        popupAnchor:
                            [0, -9]
                    });

                const marker =
                    L.marker(
                        [
                            parseFloat(
                                issue.latitude
                            ),

                            parseFloat(
                                issue.longitude
                            )
                        ],
                        {
                            icon:
                                issueIcon
                        }
                    );

                marker.bindPopup(`
                    <strong>${issue.category}</strong><br>
                    Status: ${issue.status}<br>
                    Issue ID: ${issue.issue_id}
                `);

                marker.addTo(map);

                issueMarkers.push(
                    marker
                );
            }
        );

        console.log(
            `Loaded ${result.count} nearby issue(s).`
        );

    } catch (error) {

        console.error(
            "Error loading nearby issues:",
            error
        );
    }
}


// --------------------------------------------------
// Get reporter's current location
// --------------------------------------------------

if (locationButton && locationStatus) {

    locationButton.addEventListener(
        "click",
        () => {

            if (!navigator.geolocation) {

                locationStatus.textContent =
                    "Geolocation is not supported by this browser.";

                return;
            }

            locationStatus.textContent =
                "Requesting your location...";

            navigator.geolocation.getCurrentPosition(

                (position) => {

                    const latitude =
                        position.coords.latitude;

                    const longitude =
                        position.coords.longitude;

                    // Store reporter's actual GPS location
                    reporterLatitudeInput.value =
                        latitude;

                    reporterLongitudeInput.value =
                        longitude;

                    // Initially place issue marker
                    // at reporter's location
                    initializeMap(
                        latitude,
                        longitude
                    );

                    console.log(
                        "Reporter latitude:",
                        latitude
                    );

                    console.log(
                        "Reporter longitude:",
                        longitude
                    );
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
        }
    );
}


// --------------------------------------------------
// Report submission
// --------------------------------------------------

if (reportForm) {

    reportForm.addEventListener(
        "submit",
        async (event) => {

            event.preventDefault();

            // ------------------------------------------
            // Basic location validation
            // ------------------------------------------

            if (
                !reporterLatitudeInput.value ||
                !reporterLongitudeInput.value
            ) {

                locationStatus.textContent =
                    "Please allow location access before submitting the report.";

                locationButton.focus();

                return;
            }

            if (
                !reportedLatitudeInput.value ||
                !reportedLongitudeInput.value
            ) {

                locationStatus.textContent =
                    "Please select the issue location on the map.";

                return;
            }


            // ------------------------------------------
            // Prevent repeated submissions
            // ------------------------------------------

            const submitButton =
                reportForm.querySelector(
                    'button[type="submit"], input[type="submit"]'
                );

            if (
                submitButton &&
                submitButton.disabled
            ) {
                return;
            }

            if (submitButton) {

                submitButton.disabled =
                    true;

                if (
                    submitButton.tagName ===
                    "BUTTON"
                ) {
                    submitButton.dataset.originalText =
                        submitButton.textContent;

                    submitButton.textContent =
                        "Submitting...";
                }
            }


            const formData =
                new FormData(reportForm);


            // ------------------------------------------
            // Helper: restore submit button
            // ------------------------------------------

            function restoreSubmitButton() {

                if (!submitButton) {
                    return;
                }

                submitButton.disabled =
                    false;

                if (
                    submitButton.tagName ===
                    "BUTTON"
                ) {

                    submitButton.textContent =
                        submitButton.dataset.originalText ||
                        "Submit Report";
                }
            }


            try {

                // --------------------------------------
                // First request
                //
                // Server validates the report and
                // checks D = 20m for an active Issue.
                // --------------------------------------

                const response =
                    await fetch(
                        "../../api/report-submit.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );

                const result =
                    await response.json();

                console.log(
                    "Server response:",
                    result
                );


                // --------------------------------------
                // Server-side validation failed
                // --------------------------------------

                if (!result.success) {

                    alert(
                        result.message
                    );

                    restoreSubmitButton();

                    return;
                }


                // --------------------------------------
                // Nearby active Issue found
                // --------------------------------------

                if (
                    result.report.nearby_issue_found
                ) {

                    const sameIssue =
                        confirm(
                            "A similar issue has already been reported nearby.\n\n" +
                            "Is this the same issue?"
                        );


                    // Send citizen's decision
                    formData.set(
                        "use_existing_issue",
                        sameIssue
                            ? "1"
                            : "0"
                    );


                    // ----------------------------------
                    // Final submission
                    // ----------------------------------

                    const finalResponse =
                        await fetch(
                            "../../api/report-submit.php",
                            {
                                method: "POST",
                                body: formData
                            }
                        );

                    const finalResult =
                        await finalResponse.json();

                    console.log(
                        "Final submission response:",
                        finalResult
                    );


                    if (!finalResult.success) {

                        alert(
                            finalResult.message
                        );

                        restoreSubmitButton();

                        return;
                    }


                    // ----------------------------------
                    // Successful submission
                    // ----------------------------------

                    alert(
                        finalResult.message
                    );

                    window.location.href =
                        "reports.php";

                    return;
                }


                // --------------------------------------
                // No nearby Issue found
                //
                // This was the bug in the old version.
                //
                // We must explicitly tell the server
                // that the citizen wants to create
                // a NEW Issue.
                // --------------------------------------

                formData.set(
                    "use_existing_issue",
                    "0"
                );


                console.log(
                    "No nearby existing issue found. Creating a new issue."
                );


                // --------------------------------------
                // Final submission for NEW Issue
                // --------------------------------------

                const finalResponse =
                    await fetch(
                        "../../api/report-submit.php",
                        {
                            method: "POST",
                            body: formData
                        }
                    );

                const finalResult =
                    await finalResponse.json();

                console.log(
                    "Final new-issue response:",
                    finalResult
                );


                if (!finalResult.success) {

                    alert(
                        finalResult.message
                    );

                    restoreSubmitButton();

                    return;
                }


                // --------------------------------------
                // Successful new Issue
                // --------------------------------------

                alert(
                    finalResult.message
                );

                window.location.href =
                    "reports.php";

            } catch (error) {

                console.error(
                    "Submission error:",
                    error
                );

                alert(
                    "Something went wrong while submitting the report."
                );

                restoreSubmitButton();
            }
        }
    );
}


// --------------------------------------------------
// Photo preview
// --------------------------------------------------

const photoInput =
    document.querySelector(
        "#issue-photo"
    );

const photoPreview =
    document.querySelector(
        "#photo-preview"
    );

const photoStatus =
    document.querySelector(
        "#photo-status"
    );


if (
    photoInput &&
    photoPreview &&
    photoStatus
) {

    photoInput.addEventListener(
        "change",
        () => {

            photoPreview.innerHTML =
                "";

            photoStatus.textContent =
                "";

            const file =
                photoInput.files[0];

            if (!file) {
                return;
            }

            if (
                !file.type.startsWith(
                    "image/"
                )
            ) {

                photoStatus.textContent =
                    "Please select an image file.";

                photoInput.value =
                    "";

                return;
            }

            const image =
                document.createElement(
                    "img"
                );

            image.src =
                URL.createObjectURL(
                    file
                );

            image.alt =
                "Preview of selected issue photograph";

            photoPreview.appendChild(
                image
            );

            photoStatus.textContent =
                "Photo selected successfully.";
        }
    );
}