# API test suite reference

## Intent

- Tests lock in the current intended API and data behavior so regressions fail fast in CI.
- A few checks are intentionally environment-dependent (Postgres-only constraints/indexes); those are called out explicitly.
- When schema rules, import contracts, or domain behavior change, update tests deliberately to match the new contract.

## Core business/data rules (quick glance)

These are the high-level rules the suite protects today:

- **Game import contract is strict** - `POST /api/games` accepts only valid payload shape and enum values, and rejects malformed move data.
- **Move history integrity is preserved** - persisted move count and key FEN boundaries (first `fen_before`, final `fen_after`) must match the submitted game.
- **Relational integrity is enforced** - key uniqueness constraints prevent duplicate move numbers per game and duplicate key-moment ranks per game.
- **Deletion semantics are intentional** - deleting a game/user cascades dependent records, while `manual_notes.game_id` uses `SET NULL` to preserve notes.
- **Trend summaries are append-only snapshots** - multiple summaries per user are allowed, latest selection is time-based, and records are not mutable timestamp rows.
- **Domain enums are first-class types** - model attributes round-trip as PHP enums and invalid enum values fail at hydration/casting boundaries.
- **Seeder behavior is stable and idempotent** - mistake tag seed data inserts expected taxonomy once and can be rerun safely.
- **UUID handling is preserved end-to-end** - UUID user IDs survive session persistence without truncation, and guest sessions remain valid with nullable `user_id`.

This document maps each PHPUnit test method to the behavior it protects. Tests live under `tests/Feature` and `tests/Unit`.

## Not covered yet

Deliberate gaps right now:

- **End-to-end auth-protected API flows** - no authenticated profile or authorization matrix tests yet.
- **Controller-level coverage beyond game import** - most assertions are model/data constraints plus one import endpoint.
- **Performance and large-PGN stress cases** - import tests validate correctness, not throughput or limits.
- **Error-body contract granularity** - most failing requests assert status code (`422`) but not detailed response error structure.
- **Legacy scaffold tests** - example tests still exist and are not behavior-driving.

## Base test case

| File | Role |
|------|------|
| [`TestCase.php`](TestCase.php) | Minimal Laravel base test case extending framework `BaseTestCase`; feature tests compose setup via `RefreshDatabase` and local factories/seeders. |

---

## Feature — `POST /api/games` import contract

**File:** [`Feature/GameImportTest.php`](Feature/GameImportTest.php)

Seeds a development user, submits a representative PGN-derived payload, and validates persistence plus request validation rules.

| Test | What it verifies |
|------|------------------|
| `test_creates_game_and_moves_and_returns_201` | Valid game import returns `201` and expected response shape (`game_id`, `move_count`). |
| `test_persists_correct_move_count` | Number of persisted `moves` rows matches payload move count. |
| `test_first_move_fen_before_is_starting_position` | First move starts from canonical starting FEN. |
| `test_last_move_fen_after_matches_terminal_position` | Last move ends at expected terminal FEN after submitted sequence. |
| `test_missing_required_fields_returns_422` | Empty payload fails validation. |
| `test_invalid_result_enum_returns_422` | Non-supported game result value is rejected. |
| `test_invalid_move_colour_returns_422` | Invalid move color enum is rejected. |
| `test_invalid_uci_format_returns_422` | Invalid UCI move notation is rejected. |
| `test_empty_moves_array_returns_422` | Empty move list is rejected. |

---

## Feature — schema constraints and relational integrity

### Unique constraints

**File:** [`Feature/UniqueConstraintTest.php`](Feature/UniqueConstraintTest.php)

| Test | What it verifies |
|------|------------------|
| `test_moves_game_id_move_number_must_be_unique` | Duplicate `move_number` in the same game violates DB uniqueness. |
| `test_moves_same_number_in_different_games_is_allowed` | Same move number across different games is valid. |
| `test_key_moments_game_id_rank_must_be_unique` | Key-moment rank is unique within a game. |
| `test_key_moments_same_rank_in_different_games_is_allowed` | Same key-moment rank in different games is valid. |
| `test_key_moments_rank_check_constraint_postgres_only` | Postgres CHECK enforces key-moment rank range. |
| `test_games_partial_unique_index_postgres_only` | Postgres partial unique index prevents duplicate external-import identity per user/source. |

### Cascade and nullable foreign keys

**Files:** [`Feature/CascadeDeleteTest.php`](Feature/CascadeDeleteTest.php), [`Feature/NullableFkTest.php`](Feature/NullableFkTest.php)

| Test | What it verifies |
|------|------------------|
| `test_deleting_game_cascades_to_moves` | Removing a game removes dependent moves. |
| `test_deleting_game_cascades_to_engine_analyses` | Removing a game removes analyses via move dependency. |
| `test_deleting_game_cascades_to_key_moments` | Removing a game removes associated key moments. |
| `test_deleting_user_cascades_to_games` | Removing a user removes their games. |
| `test_deleting_user_cascades_transitively_to_moves` | User deletion transitively removes nested move rows. |
| `test_deleting_game_sets_manual_note_game_id_to_null` | `manual_notes.game_id` uses `SET NULL` on game delete. |
| `test_manual_note_survives_after_game_deletion` | Manual note content and ownership survive game deletion. |

### Model relationships and UUID generation

**File:** [`Feature/ModelRelationshipTest.php`](Feature/ModelRelationshipTest.php)

| Test | What it verifies |
|------|------------------|
| `test_user_has_many_games` | User-to-games relation returns expected count/type. |
| `test_game_belongs_to_user` | Game-to-user relation resolves correctly. |
| `test_game_has_many_moves` | Game-to-moves relation returns ordered generated rows. |
| `test_move_has_one_engine_analysis` | Move-to-analysis one-to-one relation works. |
| `test_full_chain_user_game_move_engine_analysis` | Eager-loaded relation chain resolves consistent foreign keys. |
| `test_uuids_are_generated_on_create` | Core entities generate UUID-shaped identifiers on create. |

---

## Feature — enums, seeders, and append-only snapshots

### Enum casting behavior

**File:** [`Feature/EnumCastingTest.php`](Feature/EnumCastingTest.php)

| Test | What it verifies |
|------|------------------|
| `test_analysis_status_round_trips_as_enum` | `analysis_status` hydrates as `AnalysisStatus` enum. |
| `test_game_result_round_trips_as_enum` | `result` hydrates as `GameResult` enum. |
| `test_user_colour_round_trips_as_enum` | `user_colour` hydrates as `PlayerColour` enum. |
| `test_invalid_enum_value_throws_value_error` | Invalid enum value fails with language-level `ValueError`. |

### Mistake tag seeder guarantees

**File:** [`Feature/MistakeTagSeederTest.php`](Feature/MistakeTagSeederTest.php)

| Test | What it verifies |
|------|------------------|
| `test_seeder_inserts_all_tags_on_first_run` | Initial run inserts full expected tag set (12). |
| `test_seeder_is_idempotent` | Re-running seeder does not duplicate rows. |
| `test_seeder_preserves_all_slugs` | Canonical slug taxonomy is present after seeding. |

### Trend summary append-only behavior

**File:** [`Feature/TrendSummaryAppendTest.php`](Feature/TrendSummaryAppendTest.php)

| Test | What it verifies |
|------|------------------|
| `test_two_summaries_for_same_user_both_persist` | Multiple summaries per user are retained. |
| `test_latest_of_many_returns_the_newer_summary` | `latestTrendSummary` resolves newest `computed_at`. |
| `test_trend_summaries_are_append_only_no_updated_at` | Trend summaries are append-only (no mutable `updated_at` semantics). |

---

## Feature — session UUID compatibility

**File:** [`Feature/SessionsUuidTest.php`](Feature/SessionsUuidTest.php)

| Test | What it verifies |
|------|------------------|
| `test_uuid_stores_in_sessions_user_id_without_truncation` | Session table stores full UUID user IDs without truncation. |
| `test_sessions_user_id_is_nullable` | Guest sessions with `null` `user_id` are valid. |

---

## Unit

**File:** [`Unit/ExampleTest.php`](Unit/ExampleTest.php)

Contains a minimal scaffold assertion (`true` is `true`) and is not currently used to protect domain behavior.

