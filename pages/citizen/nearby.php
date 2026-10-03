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

    <link rel="stylesheet" href="../../assets/css/style.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>

<body>

    <?php include '../../includes/header.php'; ?>

    <div class="app-layout">

        <?php include '../../includes/sidebar.php'; ?>

        <main class="app-content">

            <div class="page-heading">
                <h1>Issues Near You</h1>
                <p>View public infrastructure issues reported within 500 m of your location.</p>
            </div>

            <section class="nearby-map-card">

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

    <?php include '../../includes/mobile-nav.php'; ?>

    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js">
    </script>

    <script>
        const map = L.map('nearby-map');

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
                        const marker = L.marker([
                            latitude,
                            longitude
                        ]);

                        marker.bindPopup(`
            <strong>${issue.category}</strong><br>
            Status: ${issue.status}<br>
            Issue ID: ${issue.issue_id}
        `);

                        marker.addTo(map);

                        issueMarkers.push(marker);


                        // Issue list item
                        const issueCard =
                            document.createElement('div');

                        issueCard.className =
                            'nearby-issue-card';

                        issueCard.innerHTML = `
            <div>
                <h3>${issue.category}</h3>
                <p>Status: ${issue.status}</p>
                <p>Issue ID: ${issue.issue_id}</p>
            </div>
        `;

                        issueCard.addEventListener(
                            'click',
                            function() {

                                map.setView(
                                    [latitude, longitude],
                                    18
                                );

                                marker.openPopup();
                            }
                        );

                        nearbyIssuesList.appendChild(
                            issueCard
                        );
                    });
                }

                console.log(
                    `Loaded ${result.count} nearby issue(s).`
                );

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