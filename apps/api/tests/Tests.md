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
- **Performance and large-PGN stress cases** - import tests validate correctness, not throughput or limits.
- **Real Stockfish binary integration** - analysis job tests currently mock `StockfishService` for determinism.
- **Command failure-path handling** - current command coverage asserts synchronous dispatch, not thrown-error output path.

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
| `test_dispatches_analyse_game_job_after_import` | Import dispatches the async analysis job with created game ID. |
| `test_uses_defaults_when_optional_fields_are_missing` | Optional import fields fall back to intended defaults (`opening_name`, `eco_code`, `move_count`, status/source/colour, `share_code`). |
| `test_missing_required_fields_returns_field_errors` | Validation response includes field-keyed errors for required top-level fields. |
| `test_invalid_move_fields_return_field_errors` | Validation response includes nested move field errors (`moves.0.*`) for malformed move data. |

---

## Feature — `GET /api/v1/games/{id}` and share-code lookup

**File:** [`Feature/GameShowTest.php`](Feature/GameShowTest.php)

| Test | What it verifies |
|------|------------------|
| `test_show_returns_expected_contract_and_moves_ordered_by_move_number` | Game detail endpoint returns the full game/move payload contract and deterministic move ordering. |
| `test_show_returns_404_for_unknown_uuid` | Unknown game ID returns `404`. |
| `test_show_by_share_code_returns_same_game` | Share-code route resolves to the expected game payload. |
| `test_show_by_share_code_includes_chess_com_source_url_when_present` | Chess.com-imported games expose a source URL for linking back to the original game page. |
| `test_show_by_share_code_returns_404_when_not_found` | Unknown share code returns `404`. |
| `test_share_code_lookup_is_case_sensitive` | Share-code lookup behavior is documented as exact-match (case-sensitive). |

---

## Feature — connected account listing

**File:** [`Feature/ConnectedAccountListTest.php`](Feature/ConnectedAccountListTest.php)

| Test | What it verifies |
|------|------------------|
| `test_returns_paginated_payload_with_empty_data_when_no_accounts` | List endpoint returns a stable paginated contract with empty `data` and correct `meta` defaults. |
| `test_returns_accounts_ordered_by_platform_then_username` | Accounts are returned in deterministic order (`platform`, then `username`). |

---

## Feature — connected account profile stats and filters

**File:** [`Feature/ConnectedAccountStatsTest.php`](Feature/ConnectedAccountStatsTest.php)

| Test | What it verifies |
|------|------------------|
| `test_returns_404_for_unknown_username` | Stats endpoint returns `404` for unknown connected-account usernames. |
| `test_returns_zeroed_stats_when_no_analysed_games` | No complete games returns a stable zero/null stats contract, empty trend arrays, zero `analysed_counts_by_type`, and `recommended_game_type` null. |
| `test_derives_win_loss_draw_from_board_result_and_user_colour` | W/D/L counters are computed from tracked-player perspective, not raw board winner only. |
| `test_avg_cp_loss_uses_only_tracked_player_colour_moves` | Avg CPL excludes opponent moves and uses only tracked-player move rows. |
| `test_avg_cp_loss_excludes_games_with_no_analysed_moves` | Games without analysed move CPL do not drag average toward zero. |
| `test_rating_trend_uses_user_rating_after_with_fallback` | Rating trend prefers `user_rating_after` and falls back to `user_rating_before`. |
| `test_recent_games_derives_result_from_player_perspective` | Recent games list returns player-relative `WIN/LOSS/DRAW` and expected share-code payload. |
| `test_returns_correct_aggregate_stats` | Blunder/mistake/inaccuracy averages and analysed count are aggregated correctly. |
| `test_stats_can_be_filtered_by_game_type` | `game_type` query filter scopes stats aggregates to bullet/blitz/rapid/daily buckets. |
| `test_games_endpoint_can_be_filtered_by_game_type` | Connected-account games endpoint respects `game_type` filtering for listed games. |
| `test_analysed_counts_and_recommended_type_use_time_control_buckets` | `analysed_counts_by_type` buckets complete games by time control; `recommended_game_type` picks the largest bucket. |
| `test_recommended_type_breaks_ties_in_fixed_order` | When two buckets tie for the max count, `recommended_game_type` uses a deterministic order (bullet, then blitz, then rapid, then daily). |

---

## Feature — `chess:sync-connected-account` and Chess.com archive sync

**File:** [`Feature/SyncConnectedAccountCommandTest.php`](Feature/SyncConnectedAccountCommandTest.php)

| Test | What it verifies |
|------|------------------|
| `test_full_archive_queues_all_games_across_months` | `SyncChessComAccountJob` with full archive walks every month and queues one `ImportExternalGameJob` per game. |
| `test_recent_window_caps_at_twenty_from_newest_months` | Recent-window mode matches the web sync cap (20 games, newest months first). |
| `test_command_requires_account_without_create` | Command fails when no `connected_accounts` row exists and `--create` is not passed. |
| `test_command_create_option_inserts_row_and_dispatches_job` | `--create` upserts a row and dispatches the sync job to the queue. |

---

## Feature — analysis job behavior and command wiring

**Files:** [`Feature/AnalyseGameJobTest.php`](Feature/AnalyseGameJobTest.php), [`Feature/AnalyseGameCommandTest.php`](Feature/AnalyseGameCommandTest.php), [`Feature/ReanalyseGamesCommandTest.php`](Feature/ReanalyseGamesCommandTest.php)

| Test | What it verifies |
|------|------------------|
| `test_job_sets_game_complete_and_updates_move_and_engine_analysis` | Job writes move-level analysis (`cp_score`, `cp_loss`, `classification`), computes bounded ACPL-style accuracy, updates user-colour summary counters/status, and upserts engine analysis rows. |
| `test_job_skips_complete_games_unless_forced` | Job exits early for already-complete games when not forced. |
| `test_failed_marks_game_as_failed` | Job `failed()` hook marks game analysis status as failed. |
| `test_command_dispatches_sync_analysis_job` | `chess:analyse` command dispatches `AnalyseGameJob` synchronously with expected arguments. |
| `test_command_requires_scope_option` | `chess:reanalyse` requires explicit scope (`--all` or `--game_id`) to avoid accidental full reruns. |
| `test_command_dispatches_sync_force_jobs_for_specific_game_ids` | `chess:reanalyse --game_id=...` dispatches forced sync analysis for each unique requested game ID. |
| `test_command_with_all_dispatches_for_every_game` | `chess:reanalyse --all` dispatches forced sync analysis for every game in the database. |

---

## Feature — share-code generation and migration safeguards

**Files:** [`Feature/ShareCodeGeneratorTest.php`](Feature/ShareCodeGeneratorTest.php), [`Feature/ShareCodeBackfillMigrationTest.php`](Feature/ShareCodeBackfillMigrationTest.php)

| Test | What it verifies |
|------|------------------|
| `test_generate_returns_8_char_code_with_expected_alphabet` | Generated share code matches intended 8-char unambiguous alphabet. |
| `test_generate_returns_unique_codes_across_many_calls` | Generator does not return duplicates across a representative batch. |
| `test_backfill_migration_replaces_non_8_char_share_codes_postgres_only` | Backfill migration upgrades non-8-char share codes (Postgres-only path). |

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
| `test_engine_analyses_move_id_and_engine_name_must_be_unique` | Duplicate analysis rows for the same `(move_id, engine_name)` are rejected. |
| `test_engine_analyses_allows_same_move_for_different_engines` | Multi-engine analysis rows for a single move are allowed. |

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

