
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

/* --------------------------------------------------
   Authority Issues Map
-------------------------------------------------- */

const authorityMapContainer =
    document.querySelector("#authority-map");

if (authorityMapContainer) {

    let authorityIssuesMap = null;
    let authorityIssueLayer = null;
    let authorityRegionLayer = null;

    const priorityColors = {
        Low: "#16a34a",
        Moderate: "#eab308",
        High: "#f97316",
        Critical: "#dc2626",
        Unscored: "#64748b"
    };

    // Escape database values before inserting them into popups.
    function escapeAuthorityMapHtml(value) {
        return String(value ?? "").replace(
            /[&<>"']/g,
            character => ({
                "&": "&amp;",
                "<": "&lt;",
                ">": "&gt;",
                '"': "&quot;",
                "'": "&#039;"
            })[character]
        );
    }


    // Create the status filter.
    const mapControls = document.createElement("div");

    mapControls.style.marginBottom = "12px";

    mapControls.innerHTML = `
        <label for="authority-map-status">
            <strong>Show Issues:</strong>
        </label>

        <select
            id="authority-map-status"
            style="margin-left: 8px; padding: 8px;"
        >
            <option value="active">
                Active (Open + In Progress)
            </option>

            <option value="all">All Issues</option>
            <option value="Open">Open</option>
            <option value="In Progress">In Progress</option>
            <option value="Resolved">Resolved</option>
            <option value="Closed">Closed</option>
            <option value="Rejected">Rejected</option>
        </select>
    `;

    authorityMapContainer.before(mapControls);


    // Status message for loading, errors and empty results.
    const mapMessage = document.createElement("p");

    mapMessage.setAttribute(
        "role",
        "status"
    );

    mapMessage.style.marginBottom = "10px";

    mapControls.after(mapMessage);


    // Create the priority legend.
    const mapLegend = document.createElement("div");

    mapLegend.style.display = "flex";
    mapLegend.style.flexWrap = "wrap";
    mapLegend.style.gap = "12px";
    mapLegend.style.marginTop = "12px";
    mapLegend.style.marginBottom = "12px";

    mapLegend.innerHTML = Object.entries(priorityColors)
        .map(([band, color]) => `
            <span style="
                display: inline-flex;
                align-items: center;
                gap: 5px;
            ">
                <span style="
                    width: 12px;
                    height: 12px;
                    display: inline-block;
                    border-radius: 50%;
                    background: ${color};
                "></span>

                ${band}
            </span>
        `)
        .join("");

    authorityMapContainer.after(mapLegend);


    // Initialize Leaflet only when the Authority map exists.
    function initializeAuthorityIssuesMap() {

        if (authorityIssuesMap) {
            return;
        }

        authorityIssuesMap = L.map(
            authorityMapContainer
        ).setView(
            [28.6139, 77.2090],
            11
        );

        L.tileLayer(
            "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
            {
                attribution:
                    "&copy; OpenStreetMap contributors"
            }
        ).addTo(authorityIssuesMap);

        authorityIssueLayer = L.layerGroup().addTo(
            authorityIssuesMap
        );

        // Assigned service-region boundaries.
        authorityRegionLayer = L.geoJSON([], {
            style: {
                color: "#2563eb",
                weight: 3,
                opacity: 0.9,
                fillColor: "#3b82f6",
                fillOpacity: 0.10
            },

            onEachFeature: function (feature, layer) {
                const regionName =
                    feature.properties?.name || "Assigned region";

                layer.bindTooltip(regionName);
            }
        }).addTo(authorityIssuesMap);

        authorityIssuesMap.invalidateSize();
    }


    // Fetch Issues from the existing Authority map API.
    async function loadAuthorityIssues() {

        const statusSelect =
            document.querySelector("#authority-map-status");

        const selectedStatus = statusSelect.value;

        mapMessage.textContent = "Loading Issues...";

        statusSelect.disabled = true;

        try {

            const response = await fetch(
                `../../api/authority/issues-map.php?status=${encodeURIComponent(selectedStatus)
                }`
            );

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || "Could not load Issues."
                );
            }


            initializeAuthorityIssuesMap();

            authorityIssueLayer.clearLayers();
            authorityRegionLayer.clearLayers();

            const bounds = [];

            // Draw every assigned region that has valid GeoJSON.
            (result.regions || []).forEach(region => {
                if (
                    !region.boundary ||
                    !["Polygon", "MultiPolygon"].includes(
                        region.boundary.type
                    )
                ) {
                    return;
                }

                authorityRegionLayer.addData({
                    type: "Feature",
                    properties: {
                        name: region.name,
                        region_id: region.region_id
                    },
                    geometry: region.boundary
                });
            });


            // Draw every assigned region that has valid GeoJSON.
            (result.regions || []).forEach(region => {
                if (
                    !region.boundary ||
                    !["Polygon", "MultiPolygon"].includes(
                        region.boundary.type
                    )
                ) {
                    return;
                }

                authorityRegionLayer.addData({
                    type: "Feature",
                    properties: {
                        name: region.name,
                        region_id: region.region_id
                    },
                    geometry: region.boundary
                });
            });

            const issues = result.issues || [];

            issues.forEach(issue => {

                const latitude = Number(issue.latitude);
                const longitude = Number(issue.longitude);

                if (
                    !Number.isFinite(latitude) ||
                    !Number.isFinite(longitude) ||
                    latitude < -90 ||
                    latitude > 90 ||
                    longitude < -180 ||
                    longitude > 180
                ) {
                    return;
                }

                const band = issue.priority_band || "Unscored";

                const markerColor =
                    priorityColors[band] || priorityColors.Low;

                const markerIcon = L.divIcon({

                    className: "authority-issue-marker",

                    html: `
                        <div style="
                            width: 18px;
                            height: 18px;
                            box-sizing: border-box;
                            border-radius: 50%;
                            background: ${markerColor};
                            border: 2px solid white;
                            box-shadow: 0 1px 4px rgba(0,0,0,0.5);
                        "></div>
                    `,

                    iconSize: [18, 18],
                    iconAnchor: [9, 9],
                    popupAnchor: [0, -9]
                });

                const marker = L.marker(
                    [latitude, longitude],
                    { icon: markerIcon }
                );

                const issueUrl =
                    `issue-details.php?id=${encodeURIComponent(issue.id)
                    }`;

                const popupContent = `
                    <div>
                        <strong>
                            ${escapeAuthorityMapHtml(issue.issue_id)}
                        </strong>

                        <br>

                        Category:
                        ${escapeAuthorityMapHtml(issue.category_name)}

                        <br>

                        Status:
                        ${escapeAuthorityMapHtml(issue.status)}

                        <br>

                        Priority:
                        ${issue.priority_score === null || issue.priority_score === undefined
                    ? "Unscored"
                    : escapeAuthorityMapHtml(Number(issue.priority_score).toFixed(2))}
                        (${escapeAuthorityMapHtml(band)})

                        <br>

                        Reports:
                        ${escapeAuthorityMapHtml(issue.report_count)}

                        <br><br>

                        <a href="${issueUrl}">
                            View Issue
                        </a>
                    </div>
                `;

                marker.bindPopup(popupContent);

                authorityIssueLayer.addLayer(marker);

                bounds.push([latitude, longitude]);
            });



            const regionBounds = authorityRegionLayer.getBounds();

            if (regionBounds.isValid()) {
                // Prefer the assigned region boundary so the
                // full service area remains visible.
                authorityIssuesMap.fitBounds(regionBounds, {
                    padding: [30, 30],
                    maxZoom: 14
                });
            } else if (bounds.length > 0) {
                // Fallback when no assigned boundary is available.
                if (bounds.length === 1) {
                    authorityIssuesMap.setView(bounds[0], 16);
                } else {
                    authorityIssuesMap.fitBounds(bounds, {
                        padding: [30, 30],
                        maxZoom: 16
                    });
                }
            }

            if (bounds.length > 0) {
                mapMessage.textContent =
                    `Showing ${bounds.length} Issue(s).`;
            } else {
                mapMessage.textContent =
                    "No Issues found for this filter.";
            }

            authorityIssuesMap.invalidateSize();


        } catch (error) {

            console.error(
                "Authority map error:",
                error
            );

            mapMessage.textContent =
                "Could not load the Issues map. Please try again.";

        } finally {

            statusSelect.disabled = false;
        }
    }


    // Reload markers whenever the selected status changes.
    document
        .querySelector("#authority-map-status")
        .addEventListener(
            "change",
            loadAuthorityIssues
        );


    // Load the default Active view.
    loadAuthorityIssues();
}
