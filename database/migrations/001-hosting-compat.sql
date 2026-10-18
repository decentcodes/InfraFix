-- Run ONCE on an existing local MySQL 8 database (already built from the old schema.sql).
-- Not needed for a fresh import of the new schema.sql.
ALTER TABLE regions
    ADD COLUMN boundary_geojson LONGTEXT NULL
    COMMENT 'GeoJSON Polygon/MultiPolygon, WGS84, [lng, lat]' AFTER parent_region_id;

-- Optional: keep the old spatial column for reference, or drop it:
-- ALTER TABLE regions DROP COLUMN boundary;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO app_settings (setting_key, setting_value)
VALUES ('otp_mode', 'demo'), ('weather_condition', 'Rain'), ('weather_mode', 'manual');

-- Add the remaining Delhi districts (the original seed had only 5).
SET @delhi_region_id = (SELECT id FROM regions WHERE region_id = 'REG-DELHI');
INSERT IGNORE INTO regions (region_id, name, region_type, parent_region_id)
VALUES
    ('REG-NEW-DELHI', 'New Delhi', 'District', @delhi_region_id),
    ('REG-NORTH-EAST-DELHI', 'North East Delhi', 'District', @delhi_region_id),
    ('REG-NORTH-WEST-DELHI', 'North West Delhi', 'District', @delhi_region_id),
    ('REG-SHAHDARA', 'Shahdara', 'District', @delhi_region_id),
    ('REG-SOUTH-EAST-DELHI', 'South East Delhi', 'District', @delhi_region_id),
    ('REG-SOUTH-WEST-DELHI', 'South West Delhi', 'District', @delhi_region_id);
