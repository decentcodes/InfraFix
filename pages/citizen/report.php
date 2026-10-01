<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Report an Issue — InfraFix</title>

    <link rel="stylesheet" href="../../assets/css/style.css">

    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    />
</head>

<body>

    <?php include '../../includes/header.php'; ?>


    <main class="app-layout">

        <?php include '../../includes/sidebar.php'; ?>


        <section class="app-content">

            <div class="page-heading">

                <p class="eyebrow">
                    Citizen Portal
                </p>

                <h1>
                    Report an Issue
                </h1>

                <p>
                    Help improve your area by reporting an infrastructure
                    issue at its current location.
                </p>

            </div>


            <form
                class="report-form"
                action="../../api/report-submit.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <div class="form-section">

                    <h2>Issue details</h2>

                    <div class="form-group">

                        <label for="issue-category">
                            Issue category
                        </label>

                        <select id="issue-category" name="issue_category" required>

                            <option value="">
                                Select an issue
                            </option>

                            <option value="pothole">
                                Pothole
                            </option>

                            <option value="open-manhole">
                                Open manhole
                            </option>

                            <option value="broken-streetlight">
                                Broken streetlight
                            </option>

                            <option value="garbage">
                                Garbage accumulation
                            </option>

                            <option value="water-leakage">
                                Water leakage
                            </option>

                            <option value="exposed-wiring">
                                Exposed electrical wiring
                            </option>

                            <option value="blocked-drainage">
                                Blocked drainage
                            </option>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="issue-description">
                            Description <span>(Optional)</span>
                        </label>

                        <textarea
                            id="issue-description"
                            name="description"
                            rows="5"
                            maxlength="500"
                            placeholder="Add any additional information that may help the authority understand the issue."
                        ></textarea>

                        <p class="form-hint">
                            Maximum 500 characters.
                        </p>

                    </div>

                </div>


                <div class="form-section">

                    <h2>Location</h2>

                    <p>
                        First detect your current location, then place the pin
                        on the map where the issue is actually located.
                    </p>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="get-location"
                    >
                        Use my current location
                    </button>

                    <p id="location-status" class="form-status">
                        Location not detected yet.
                    </p>

                    <div
                        id="report-map"
                        class="report-map"
                        aria-label="Map for selecting the issue location"
                    ></div>

                    <p class="form-hint">
                        Drag the marker to the exact location of the issue.
                    </p>

                    <!-- Current GPS location of the reporter -->
                    <input
                        type="hidden"
                        id="reporter-latitude"
                        name="reporter_latitude"
                    >

                    <input
                        type="hidden"
                        id="reporter-longitude"
                        name="reporter_longitude"
                    >

                    <!-- Location selected by the reporter -->
                    <input
                        type="hidden"
                        id="reported-latitude"
                        name="reported_latitude"
                    >

                    <input
                        type="hidden"
                        id="reported-longitude"
                        name="reported_longitude"
                    >

                </div>


                <div class="form-section">

                    <h2>Photo</h2>

                    <p>
                        Add a photograph showing the reported issue.
                    </p>

                    <div class="form-group">

                        <label for="issue-photo">
                            Photograph of the issue
                        </label>

                        <input
                            type="file"
                            id="issue-photo"
                            name="issue_photo"
                            accept="image/*"
                            capture="environment"
                            required
                        >

                        <p class="form-hint">
                            Maximum file size: 5 MB.
                        </p>

                    </div>

                    <div id="photo-preview" class="photo-preview"></div>

                    <p id="photo-status" class="form-status"></p>

                </div>


                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Submit Report
                    </button>

                </div>

            </form>

        </section>

    </main>


    <?php include '../../includes/mobile-nav.php'; ?>

    <script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    ></script>
    <script src="../../assets/js/app.js"></script>

</body>

</html>