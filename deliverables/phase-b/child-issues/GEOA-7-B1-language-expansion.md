# GEOA-7-B1 — Language Expansion 25→70

**Parent**: GEOA-7 (Phase B CTO Technical Execution Plan)
**Owner**: Staff Engineer (Pipeline)
**Sprint**: Sprint 3 (Week 3) + Sprint 4 (Week 4)
**Depends On**: B5 (Infrastructure — Horizon queues, claude_local, database)
**Blocks**: B3 (optimization engine builds on translation pipeline patterns)

## Objective

Expand language support from 25 to 70 languages across three quality tiers. Every language must pass a quality gate (≥80% of high-resource baseline) without degrading the existing 25 languages. Every optimization result includes before/after scores.

## Technical Specification

See `deliverables/phase-b/cto-technical-execution-plan.md` Section 2.3 for full translation pipeline architecture, tier definitions, database schema, job design, quality gate logic, and edge case matrix.

### Key Components to Build

1. **Translation Pipeline**
   - `TranslationManager` orchestrator dispatching to tiered Horizon queues
   - Tier 1 (30 languages): Full DeepSeek translation + auto QA
   - Tier 2 (35 languages): DeepSeek + terminology validation + English fallback for low-confidence segments
   - Tier 3 (5 languages): Machine translation baseline + English fallback annotation

2. **TranslateStringJob** (Horizon job)
   - Dedup check → AI translation → Quality scoring → Persist to DB → Cache warm
   - Retry with exponential backoff (3 attempts max)
   - Placeholder extraction/reinsertion for HTML/ICU syntax in translation values

3. **QualityGate Service**
   - COMET score computation per translation
   - Per-language quality threshold enforcement
   - Regression test: re-score all 25 existing languages, assert ≤2% quality delta
   - Auto-flag for human review when score < 0.3 (likely hallucination)

4. **LanguageRegistry**
   - Config-driven language definitions (code, name, native_name, tier, fallback_locale)
   - `php artisan lang:expand {code}` CLI command to trigger pipeline for new language

5. **Database Schema**
   - `languages` table: code, name, native_name, tier, is_active, quality_score, baseline_score
   - `translations` table: locale, namespace, key, value, source_value, quality_score, is_machine_translated
   - Unique constraint on (locale, namespace, key)

6. **TranslationCache** (Redis)
   - 30-day TTL per translation entry
   - Cache warming on write (job completion)
   - Cache invalidation on translation update

### Quality Tiers

| Tier | Count | Strategy | Quality Gate |
|------|-------|----------|-------------|
| 1 | 30 | Full DeepSeek translation + auto QA | COMET ≥ 0.85 |
| 2 | 35 | DeepSeek + term validation + English fallback | COMET ≥ 80% of Tier 1 baseline |
| 3 | 5 | Machine translation + English fallback annotation | ≥ 70% of strings translated |

## Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B1.1 | 70 languages loadable via locale switch | Automated test: iterate all 70, assert HTTP 200 |
| B1.2 | Tier 1 languages: COMET score ≥ 0.85 | QualityGate report output |
| B1.3 | Tier 2 languages: COMET score ≥ 80% of Tier 1 baseline | QualityGate report output |
| B1.4 | Tier 3 fallback: untranslated keys display English text (not blank, not crash) | Visual inspection |
| B1.5 | Existing 25 languages show zero regression (≤2% quality delta) | Regression test output (must be empty) |
| B1.6 | RTL languages render with correct text direction | Screenshot of Arabic page |
| B1.7 | Translation pipeline processes 10k keys in < 1 hour | Timed batch run with measurement |
| B1.8 | Every optimization result includes before/after scores in DB | SQL query on optimization_results table |

## Edge Cases

| Scenario | Handling |
|----------|----------|
| claude_local timeout during translation | Retry 3x with exponential backoff + jitter; fail → mark untranslated, English fallback |
| Translation key contains HTML tags or ICU placeholders | Preprocess: extract → translate text → reinsert → validate structure intact |
| Plural forms differ across languages | ICU MessageFormat syntax in translation values; Laravel pluralization helper |
| Gender-specific language requirements | Prompt DeepSeek with gender-neutral instructions; tag gendered languages |
| Same English text used in different UI contexts | Translation key includes context namespace: `button.submit` vs `link.submit` |
| DeepSeek returns hallucination/garbage | QualityGate score < 0.3 → auto-retry with different prompt; 3 failures → flag for review |
| New language added after initial 70-language deploy | LanguageRegistry is config-driven; `php artisan lang:expand {code}` triggers full pipeline |
| Horizon worker crashes mid-translation | Job is `Attempts=3` with backoff; Horizon auto-retries; partial results not saved (transactional) |

## Definition of Done

- [ ] All 70 languages defined in LanguageRegistry
- [ ] Translation pipeline processes all 70 languages through QualityGate
- [ ] Tier 1: 30 languages with COMET ≥ 0.85
- [ ] Tier 2: 35 languages with COMET ≥ 80% of Tier 1 baseline, "Beta" badge shown
- [ ] Tier 3: 5 languages with graceful English fallback
- [ ] Regression test passed: existing 25 languages within 2% of baseline
- [ ] TranslationCache operational with 30-day TTL
- [ ] CLI command `lang:expand` functional for future language additions
- [ ] All 8 acceptance criteria verified by QA Engineer
