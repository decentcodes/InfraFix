-- Run once on your existing local database.
-- If it fails with a duplicate-entry error, delete the duplicate feedback rows first.
ALTER TABLE resolution_feedback
    ADD CONSTRAINT uq_resolution_feedback_once UNIQUE (resolution_id, citizen_id);
