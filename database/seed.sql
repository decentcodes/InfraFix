-- InfraFix V1 seed data
-- Finalized prioritization configuration

USE infrafix;


-- ============================================================
-- CATEGORIES
-- ============================================================

INSERT INTO categories
    (category_id, name, slug)
VALUES
    ('CAT-POTHOLE', 'Pothole', 'pothole'),
    ('CAT-MANHOLE', 'Open Manhole', 'open-manhole'),
    ('CAT-STREETLIGHT', 'Broken Streetlight', 'broken-streetlight'),
    ('CAT-GARBAGE', 'Garbage Accumulation', 'garbage'),
    ('CAT-WATER', 'Water Leakage', 'water-leakage'),
    ('CAT-WIRING', 'Exposed Electrical Wiring', 'exposed-wiring'),
    ('CAT-DRAINAGE', 'Blocked Drainage', 'blocked-drainage');


-- ============================================================
-- PRIORITY FACTORS
-- ============================================================

INSERT INTO priority_factors
    (factor_id, name, slug, max_level, max_contribution)
VALUES
    ('PF-SAFETY', 'Safety', 'safety', 5, 40),
    ('PF-LOCATION', 'Location Criticality', 'location-criticality', 5, 20),
    ('PF-SEVERITY', 'Severity', 'severity', 5, 15),
    ('PF-WEATHER', 'Weather/Context', 'weather-context', 4, 10),
    ('PF-REPORTS', 'Report Volume', 'report-volume', 10, 10),
    ('PF-DURATION', 'Duration', 'duration', 5, 5);


-- ============================================================
-- CATEGORY SAFETY / SEVERITY BASELINES
-- ============================================================

-- Safety
INSERT INTO category_priority_baselines
    (category_id, factor_id, base_level)
SELECT c.id, pf.id,
       CASE c.slug
           WHEN 'pothole' THEN 3
           WHEN 'open-manhole' THEN 5
           WHEN 'broken-streetlight' THEN 1
           WHEN 'garbage' THEN 0
           WHEN 'water-leakage' THEN 0
           WHEN 'exposed-wiring' THEN 4
           WHEN 'blocked-drainage' THEN 0
       END
FROM categories c
CROSS JOIN priority_factors pf
WHERE pf.slug = 'safety';


-- Severity
INSERT INTO category_priority_baselines
    (category_id, factor_id, base_level)
SELECT c.id, pf.id,
       CASE c.slug
           WHEN 'pothole' THEN 3
           WHEN 'open-manhole' THEN 1
           WHEN 'broken-streetlight' THEN 1
           WHEN 'garbage' THEN 4
           WHEN 'water-leakage' THEN 2
           WHEN 'exposed-wiring' THEN 1
           WHEN 'blocked-drainage' THEN 3
       END
FROM categories c
CROSS JOIN priority_factors pf
WHERE pf.slug = 'severity';


-- ============================================================
-- REPORT VOLUME LEVELS
-- ============================================================

INSERT INTO priority_report_levels
    (min_reports, max_reports, level)
VALUES
    (1,    1,    0),
    (2,    4,    1),
    (5,    9,    2),
    (10,   24,   3),
    (25,   49,   4),
    (50,   99,   5),
    (100,  199,  6),
    (200,  499,  7),
    (500,  999,  8),
    (1000, 2499,  9),
    (2500, NULL, 10);


-- ============================================================
-- DURATION LEVELS
-- ============================================================

INSERT INTO priority_duration_levels
    (min_days, max_days, level)
VALUES
    (0,  0,    0),
    (1,  3,    1),
    (4,  7,    2),
    (8,  14,   3),
    (15, 30,   4),
    (31, NULL, 5);


-- ============================================================
-- POI TYPES
-- ============================================================

INSERT INTO poi_types
    (poi_type_id, name, base_criticality)
VALUES
    ('POI-HOSPITAL', 'Hospital', 5),
    ('POI-SCHOOL', 'School', 5),
    ('POI-MARKET', 'Market / Public Gathering Area', 4),
    ('POI-EMERGENCY', 'Emergency / Public-Safety Facility', 3),
    ('POI-TRANSPORT', 'Major Transport Facility', 3),
    ('POI-OTHER', 'Other', 0);


-- ============================================================
-- POI DISTANCE RULES
-- ============================================================

INSERT INTO poi_distance_rules
    (min_distance_m, max_distance_m, distance_factor)
VALUES
    (0,   50,  1.0),
    (50,  100, 0.8),
    (100, 200, 0.6),
    (200, 300, 0.3),
    (300, NULL, 0.0);


-- ============================================================
-- WEATHER / CONTEXT RULES
-- ============================================================

-- Rain
INSERT INTO priority_weather_rules
    (category_id, weather_condition, context_level)
SELECT c.id, 'rain',
       CASE c.slug
           WHEN 'pothole' THEN 2
           WHEN 'open-manhole' THEN 4
           WHEN 'broken-streetlight' THEN 2
           WHEN 'garbage' THEN 2
           WHEN 'water-leakage' THEN 3
           WHEN 'exposed-wiring' THEN 4
           WHEN 'blocked-drainage' THEN 4
       END
FROM categories c;


-- Snow
INSERT INTO priority_weather_rules
    (category_id, weather_condition, context_level)
SELECT c.id, 'snow',
       CASE c.slug
           WHEN 'pothole' THEN 3
           WHEN 'open-manhole' THEN 2
           WHEN 'broken-streetlight' THEN 1
           WHEN 'garbage' THEN 1
           WHEN 'water-leakage' THEN 2
           WHEN 'exposed-wiring' THEN 2
           WHEN 'blocked-drainage' THEN 2
       END
FROM categories c;