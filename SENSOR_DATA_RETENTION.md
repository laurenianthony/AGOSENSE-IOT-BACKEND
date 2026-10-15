# Sensor data retention plan

AGOSENSE stores recent raw samples in `sensor_readings` and scheduled rollups in
`hourly_sensor_summaries` and `daily_sensor_summaries`. The `(station_id,
recorded_at)` index supports station history filters and rollup scans. Hourly
summaries are maintained for recent history; daily summaries preserve long-term
trends.

## Safe archival path for data older than 30 days

Do not delete raw samples directly. When retention is enabled in production:

1. Create a `sensor_reading_archives` table with the raw measurement fields and
   a unique `source_reading_id`; retain a station code snapshot so an archive is
   still understandable if a station is later removed.
2. In a scheduled database transaction, select rows older than 30 days in
   bounded ID batches, insert them into the archive with `ON CONFLICT DO
   NOTHING`, verify the inserted/source counts, and only then delete those same
   source IDs from `sensor_readings`.
3. Keep daily summaries before moving the raw samples and expose archived raw
   samples through the records endpoint when an operator requests a date range
   older than 30 days.
4. Retain a database backup and compare source/archive counts before enabling
   deletion. For higher ingest rates, migrate to monthly PostgreSQL range
   partitions on `recorded_at`; partition detach/drop can replace row-by-row
   archival once partition lifecycle and backups are established.

The current aggregation command does not delete or mutate raw readings. This
keeps the first rollout reversible while the archive path and retention period
are reviewed against municipal data-retention requirements.
