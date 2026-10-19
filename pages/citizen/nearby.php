<?php

require_once __DIR__ . '/../../includes/auth.php';

requireCitizenAuth();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nearby Issues - InfraFix</title>
    <?php require_once __DIR__ . '/../../includes/favicon.php'; ?>

    <link rel="stylesheet" href="../../assets/css/style.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>

<body>

    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="app-layout">

        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <main class="app-content">

            <div class="page-heading">
                <h1>Issues Near You</h1>
                <p>View public infrastructure issues reported within 500 m of your location.</p>
            </div>


            <section class="nearby-map-card">

                <div class="nearby-map-legend">
                    <strong>Priority</strong>

                    <span>
                        <i class="priority-dot priority-low"></i>
                        Low
                    </span>

                    <span>
                        <i class="priority-dot priority-moderate"></i>
                        Moderate
                    </span>

                    <span>
                        <i class="priority-dot priority-high"></i>
                        High
                    </span>

                    <span>
                        <i class="priority-dot priority-critical"></i>
                        Critical
                    </span>

                    <span>
                        <i class="priority-dot priority-unscored"></i>
                        Unscored
                    </span>
                </div>

                <div id="nearby-map"></div>

            </section>


            <section class="nearby-issues-section">

                <div class="section-heading-row">
                    <div>
                        <h2>Nearby Issues</h2>
                        <p>Issues reported around your current location.</p>
                    </div>

                    <span class="nearby-count" id="nearby-count">
                        0 issues
                    </span>
                </div>

                <div class="nearby-empty-state" id="nearby-empty-state">
                    <h3>No nearby issues</h3>
                    <p>
                        There are currently no reported infrastructure issues
                        within 500 m of your location.
                    </p>
                </div>

                <div class="nearby-issues-list" id="nearby-issues-list"></div>

            </section>

        </main>

    </div>

    <?php include __DIR__ . '/../../includes/mobile-nav.php'; ?>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    <script>
        const map = L.map('nearby-map');

        const priorityColors = {
            Low: '#26965c',
            Moderate: '#e5b52e',
            High: '#ed8528',
            Critical: '#d83b43',
            Unscored: '#64748b'
        };

        function getPriorityColor(band) {
            return priorityColors[band] || priorityColors.Unscored;
        }


        let issueMarkers = [];

        const nearbyCount =
            document.querySelector('#nearby-count');

        const nearbyEmptyState =
            document.querySelector('#nearby-empty-state');

        const nearbyIssuesList =
            document.querySelector('#nearby-issues-list');

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        if (!navigator.geolocation) {
            map.setView([28.6139, 77.2090], 16);
            alert('Location services are not supported by your browser.');
        } else {

            navigator.geolocation.getCurrentPosition(
                function(position) {

                    const latitude = position.coords.latitude;
                    const longitude = position.coords.longitude;

                    map.setView([latitude, longitude], 16);

                    L.marker([latitude, longitude])
                        .addTo(map)
                        .bindPopup('You are here')
                        .openPopup();

                    L.circle([latitude, longitude], {
                        radius: 500
                    }).addTo(map);

                    loadNearbyIssues(latitude, longitude);

                },

                function(error) {

                    map.setView([28.6139, 77.2090], 16);

                    alert(
                        'We could not access your location. ' +
                        'Please allow location access to view nearby issues.'
                    );

                }
            );
        }

        async function loadNearbyIssues(latitude, longitude) {

            try {

                const response = await fetch(
                    `../../api/issues-nearby.php?latitude=${encodeURIComponent(latitude)}&longitude=${encodeURIComponent(longitude)}`
                );

                const result = await response.json();

                if (!result.success) {
                    console.error(
                        'Could not load nearby issues:',
                        result.message
                    );
                    return;
                }

                // Remove old Issue markers.
                issueMarkers.forEach((marker) => {
                    map.removeLayer(marker);
                });

                issueMarkers = [];
                nearbyIssuesList.innerHTML = '';

                nearbyCount.textContent =
                    `${result.count} issue${result.count === 1 ? '' : 's'}`;

                if (result.count === 0) {

                    nearbyEmptyState.style.display = 'block';

                } else {

                    nearbyEmptyState.style.display = 'none';

                    result.issues.forEach((issue) => {

                        const latitude =
                            parseFloat(issue.latitude);

                        const longitude =
                            parseFloat(issue.longitude);



                        // Map marker
                        const priorityBand = issue.priority_band || 'Unscored';
                        const priorityScore = issue.priority_score;
                        const markerColor = getPriorityColor(priorityBand);

                        const marker = L.circleMarker(
                            [latitude, longitude], {
                                radius: 9,
                                color: markerColor,
                                fillColor: markerColor,
                                fillOpacity: 0.85,
                                weight: 2
                            }
                        );

                        const scoreText = priorityScore !== null &&
                            priorityScore !== undefined ?
                            Number(priorityScore).toFixed(1) :
                            'Not available';

                        marker.bindPopup(`
    <strong>${issue.category}</strong><br>
    Status: ${issue.status}<br>
    Issue ID: ${issue.issue_id}<br>
    Priority: ${priorityBand}
`);

                        marker.addTo(map);
                        issueMarkers.push(marker);


                        // Issue list item
                        const issueCard = document.createElement('article');
                        issueCard.className = 'nearby-issue-card';


                        issueCard.innerHTML = `
    <div class="nearby-issue-card-content">
        <h3></h3>
        <p class="issue-status"></p>
        <p class="issue-id"></p>

        <p class="nearby-priority">
            <span
                class="priority-dot"
                style="background-color: ${markerColor}"
            ></span>
            <strong></strong>
        </p>

        <button
            type="button"
            class="btn btn-secondary view-issue-details">
            View issue details
        </button>
    </div>

    <div class="nearby-issue-details" hidden>
        <div class="nearby-issue-details-header">
            <h3 class="detail-category">Issue Details</h3>
            <button
                type="button"
                class="btn btn-secondary close-issue-details">
                Close
            </button>
        </div>

        <div class="nearby-issue-details-content"></div>
    </div>
`;


                        // Populate text safely.
                        issueCard.querySelector('h3').textContent =
                            issue.category;

                        issueCard.querySelector('.issue-status').textContent =
                            `Status: ${issue.status}`;

                        issueCard.querySelector('.issue-id').textContent =
                            `Issue ID: ${issue.issue_id}`;

                        issueCard.querySelector('.nearby-priority strong')
                            .textContent = `${priorityBand} Priority`;

                        /*
                         * Clicking the card focuses the map only.
                         */
                        function focusIssueOnMap() {
                            map.setView([latitude, longitude], 18);
                            marker.openPopup();
                        }

                        issueCard.addEventListener('click', function(event) {
                            if (event.target.closest('.view-issue-details')) {
                                return;
                            }

                            focusIssueOnMap();
                        });

                        /*
                         * Fetch and display full details only when
                         * the citizen clicks the details button.
                         */
                        const detailsButton =
                            issueCard.querySelector('.view-issue-details');

                        detailsButton.addEventListener('click', async function(event) {
                            // Collapse any other expanded issue details.
                            document.querySelectorAll('.nearby-issue-details').forEach(function(panel) {
                                panel.hidden = true;
                            });
                            event.stopPropagation();

                            const detailsPanel =
                                issueCard.querySelector('.nearby-issue-details');

                            const detailsContent =
                                issueCard.querySelector('.nearby-issue-details-content');

                            const detailsHeading =
                                issueCard.querySelector('.detail-category');

                            detailsButton.disabled = true;
                            detailsButton.textContent = 'Loading details...';

                            try {
                                const response = await fetch(
                                    `../../api/issue-details.php?issue_id=${
                                        encodeURIComponent(issue.issue_id)
                                    }`
                                );

                                const result = await response.json();

                                if (!response.ok || !result.success) {
                                    throw new Error(
                                        result.message || 'Could not load issue details.'
                                    );
                                }

                                const details = result.issue;

                                detailsHeading.textContent =
                                    `${details.category_name} — Issue Details`;

                                detailsContent.replaceChildren();

                                function addDetail(label, value) {
                                    const row = document.createElement('p');
                                    const strong = document.createElement('strong');

                                    strong.textContent = `${label}: `;

                                    row.appendChild(strong);
                                    row.appendChild(
                                        document.createTextNode(
                                            value ?? 'Not available'
                                        )
                                    );

                                    detailsContent.appendChild(row);
                                }

                                function formatDateTime(value) {
                                    if (!value) {
                                        return 'Not available';
                                    }

                                    // Interpret MySQL's timestamp as a
                                    // local date/time without changing its
                                    // displayed clock time through UTC parsing.
                                    const date = new Date(
                                        value.replace(' ', 'T')
                                    );

                                    if (Number.isNaN(date.getTime())) {
                                        return value;
                                    }

                                    return date.toLocaleString();
                                }

                                function calculateDuration(value) {
                                    if (!value) {
                                        return 'Not available';
                                    }

                                    const start = new Date(
                                        value.replace(' ', 'T')
                                    );

                                    if (Number.isNaN(start.getTime())) {
                                        return 'Not available';
                                    }

                                    const elapsed = Math.max(
                                        0,
                                        Date.now() - start.getTime()
                                    );

                                    const totalHours = Math.floor(
                                        elapsed / (1000 * 60 * 60)
                                    );

                                    const days = Math.floor(totalHours / 24);
                                    const hours = totalHours % 24;

                                    if (days === 0) {
                                        return `${hours} hour(s)`;
                                    }

                                    return `${days} day(s), ${hours} hour(s)`;
                                }

                                addDetail('Issue ID', details.issue_id);
                                addDetail('Category', details.category_name);
                                addDetail('Status', details.status);
                                addDetail(
                                    'Responsible authority',
                                    details.authority_name
                                );
                                addDetail(
                                    'Priority',
                                    details.priority_band === 'Unscored' ?
                                    'Not yet scored' :
                                    `${details.priority_band} Priority`
                                );
                                addDetail(
                                    'Duration',
                                    calculateDuration(details.first_reported_at)
                                );
                                addDetail(
                                    'First reported',
                                    formatDateTime(details.first_reported_at)
                                );
                                addDetail(
                                    'Last reported',
                                    formatDateTime(details.last_reported_at)
                                );
                                addDetail('Number of reports', details.report_count);
                                addDetail(
                                    'Location address',
                                    details.address || 'Address not available'
                                );

                                // First report description.
                                const descriptionHeading =
                                    document.createElement('h3');

                                descriptionHeading.textContent =
                                    'First report description';

                                detailsContent.appendChild(descriptionHeading);

                                const description =
                                    document.createElement('p');

                                description.textContent =
                                    details.first_description ||
                                    'No description available.';

                                detailsContent.appendChild(description);

                                // First evidence photo, if available.
                                const photoHeading =
                                    document.createElement('h3');

                                photoHeading.textContent =
                                    'First evidence photo';

                                detailsContent.appendChild(photoHeading);

                                if (
                                    details.first_evidence_photo &&
                                    !details.first_evidence_photo.startsWith('/') &&
                                    !details.first_evidence_photo.includes('..')
                                ) {
                                    const photo =
                                        document.createElement('img');

                                    photo.src =
                                        `../../${details.first_evidence_photo}`;

                                    photo.alt =
                                        `Evidence for issue ${details.issue_id}`;

                                    photo.loading = 'lazy';
                                    photo.className = 'nearby-evidence-photo';

                                    photo.onerror = function() {
                                        photo.remove();

                                        const message =
                                            document.createElement('p');

                                        message.textContent =
                                            'The evidence photo could not be loaded.';

                                        detailsContent.appendChild(message);
                                    };

                                    detailsContent.appendChild(photo);
                                } else {
                                    const noPhoto =
                                        document.createElement('p');

                                    noPhoto.textContent =
                                        'No evidence photo available.';

                                    detailsContent.appendChild(noPhoto);
                                }

                                detailsPanel.hidden = false;

                                focusIssueOnMap();

                            } catch (error) {
                                console.error(
                                    'Could not load issue details:',
                                    error
                                );

                                alert(
                                    error.message ||
                                    'Could not load issue details. Please try again.'
                                );
                            } finally {
                                detailsButton.disabled = false;
                                detailsButton.textContent = 'View issue details';
                            }
                        });

                        issueCard
                            .querySelector('.close-issue-details')
                            .addEventListener('click', function(event) {
                                event.stopPropagation();

                                issueCard.querySelector(
                                    '.nearby-issue-details'
                                ).hidden = true;
                            });

                        nearbyIssuesList.appendChild(issueCard);


                    });
                }


            } catch (error) {

                console.error(
                    'Error loading nearby issues:',
                    error
                );

            }
        }
    </script>
</body>

</html>